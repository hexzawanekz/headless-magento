<?php
namespace StripeIntegration\Payments\Model\Creditmemo\Total;

use Magento\Tax\Model\Config;

class InitialFee extends \Magento\Sales\Model\Order\Total\AbstractTotal
{
    private $helper;
    private $taxConfig;

    public function __construct(
        \StripeIntegration\Payments\Helper\InitialFee $helper,
        Config $taxConfig
    )
    {
        $this->helper = $helper;
        $this->taxConfig = $taxConfig;
    }

    /**
     * @return $this
     */
    public function collect(
        \Magento\Sales\Model\Order\Creditmemo $creditmemo
    ) {
        $baseAmount = $this->helper->getTotalInitialFeeForCreditmemo($creditmemo, false);
        $taxDetails = $this->helper->getTotalInitialFeeTaxForCreditmemo($creditmemo);
        if (is_numeric($creditmemo->getBaseToOrderRate()))
            $amount = round(floatval($baseAmount * $creditmemo->getBaseToOrderRate()), 4);
        else if (is_numeric($creditmemo->getBaseToQuoteRate()))
            $amount = round(floatval($baseAmount * $creditmemo->getBaseToQuoteRate()), 4);
        else
            $amount = $baseAmount;

        $useTaxDetails = is_array($taxDetails);

        // Same principle as for the invoice collector
        if ($this->taxConfig->priceIncludesTax() && $useTaxDetails) {
            $amount -= $taxDetails['tax'];
            $baseAmount -= $taxDetails['base_tax'];
        }

        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $amount);
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseAmount);

        $orderTaxInvoiced = $creditmemo->getOrder()->getTaxInvoiced();
        $orderTaxRefunded = $creditmemo->getOrder()->getTaxRefunded();
        $creditmemoTax = $creditmemo->getTaxAmount();

        // If the credit memo tax amount is not the same as the order invoiced tax, it means that the credit memo is
        // partial one. In this case we will add the initial fee tax here as it won't be taken from the total of
        // the order
        if ((($orderTaxInvoiced - $orderTaxRefunded) != $creditmemoTax) && $useTaxDetails) {
            $creditmemo->setTaxAmount($creditmemo->getTaxAmount() + $taxDetails['tax']);
            $creditmemo->setBaseTaxAmount($creditmemo->getBaseTaxAmount() + $taxDetails['base_tax']);
            $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $taxDetails['tax']);
            $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $taxDetails['base_tax']);
        }

        // The credit memo table does not have these fields, but we set them so that we can send them forward
        // for tax reversal calculation
        if ($amount > 0) {
            $creditmemo->setInitialFee($amount);
        }
        if ($useTaxDetails) {
            $creditmemo->setInitialFeeTax($taxDetails['tax']);
        }

        return $this;
    }
}
