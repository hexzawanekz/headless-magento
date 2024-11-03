<?php
namespace StripeIntegration\Payments\Model\Invoice\Total;

use Magento\Tax\Model\Config;
use StripeIntegration\Payments\Model\InitialFee as InitialFeeModel;

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
        \Magento\Sales\Model\Order\Invoice $invoice
    ) {

        $baseAmount = $this->helper->getTotalInitialFeeForInvoice($invoice, false);

        if (is_numeric($invoice->getBaseToOrderRate()))
            $amount = round(floatval($baseAmount * $invoice->getBaseToOrderRate()), 4);
        else
            $amount = $baseAmount;

        // The only way we can buy subscriptions is by using the Stripe payment method, which means that the invoice
        // that is being created is a full invoice and no partial invoices will be created after this one. Because of
        // this, we know that the items for the invoice are in the same qty as the ones on the order.
        // In case we have tax inclusive prices, the amount for the initial fee needs to have the initial fee tax
        // subtracted from it, as the full tax amount will be added later to the invoice
        // (vendor/magento/module-sales/Model/Order/Invoice/Total/Tax.php:116) and that will contain the
        // tax on the initial fee.
        if ($this->taxConfig->priceIncludesTax()) {
            // In case the tax was calculated with Stripe tax, use the calculated tax values for initial fee to
            // subtract from the initial fee price, as the tax was already added to the grand total in the Stripe Tax
            // module.
            if ($additionalFeesTax = $invoice->getAdditionalFeesTax()) {
                $initialFeeTax = 0;
                $baseInitialFeeTax = 0;
                if (isset($additionalFeesTax[InitialFeeModel::INITIAL_FEE_TYPE])) {
                    $initialFeeTax = $additionalFeesTax[InitialFeeModel::INITIAL_FEE_TYPE]['tax'];
                    $baseInitialFeeTax = $additionalFeesTax[InitialFeeModel::INITIAL_FEE_TYPE]['base_tax'];
                }
            } else {
                $order = $invoice->getOrder();
                $initialFeeTax = $order->getInitialFeeTax();
                $baseInitialFeeTax = $order->getBaseInitialFeeTax();
            }

            $amount -= $initialFeeTax;
            $baseAmount -= $baseInitialFeeTax;
        }

        $invoice->setGrandTotal($invoice->getGrandTotal() + $amount);
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseAmount);

        return $this;
    }
}
