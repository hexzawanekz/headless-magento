<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\Tax\SubscriptionInitialFee;

class BaseSubscription extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $subscriptions;
    private $subscriptionProductFactory;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->subscriptions = $this->objectManager->get(\StripeIntegration\Payments\Helper\Subscriptions::class);
        $this->subscriptionProductFactory = $this->objectManager->get(\StripeIntegration\Payments\Model\SubscriptionProductFactory::class);
    }

    public function compareSubscriptionDetails($order, $details)
    {
        $orderItem = null;
        foreach ($order->getAllItems() as $item)
        {
            if ($item->getSku() == $details['sku'])
                $orderItem = $item;
        }
        $this->assertNotEmpty($orderItem);

        $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromOrderItem($orderItem);
        $subscriptionProfile = $this->subscriptions->getSubscriptionDetails($subscriptionProductModel, $order, $orderItem);

        $taxes = $this->calculateTaxes($details);

        $expectedProfile = [
            "qty" => $details['qty'],
            "amount_magento" => $details['price'],
            "amount_stripe" => $details['price'] * 100,
            "initial_fee_stripe" => $details['initial_fee'] * $details['qty'] * 100,
            "initial_fee_magento" => $details['initial_fee'] * $details['qty'],
            "discount_amount_magento" => $details['discount'],
            "discount_amount_stripe" => $details['discount'] * 100,
            "shipping_magento" => $details['shipping'] * $details['qty'],
            "shipping_stripe" => $details['shipping'] * $details['qty'] * 100,
            "currency" => "usd",
            "tax_percent" => $details['tax_percent'],
            "tax_amount_item" => $taxes['item_tax'],
            "tax_amount_shipping" => $taxes['shipping_tax'],
            "tax_amount_initial_fee" => $taxes['initial_fee_tax'],
        ];

        foreach ($expectedProfile as $key => $value)
        {
            $this->assertEquals($value, $subscriptionProfile[$key], $key);
        }
    }

    private function getCalculatedTax($price, $details)
    {
        if ($details['mode'] == "exclusive") {
            return round($price * $details['tax_percent'] / 100 * $details['qty'], 2);
        } else {
            return round((($price - ($price / (1 + $details['tax_percent'] / 100))) * $details['qty']), 2);
        }
    }

    /**
     * Implement the Stripe tax calculation algorithm to have as close results as possible
     *
     * Each line item's tax is calculated individually, and rounded.
     * Line items with the same tax rate are aggregated together, and the aggregated tax is calculated.
     * If the aggregated tax differs from the sum of the individual taxes,
     * the individual taxes are adjusted (adjusting the largest amount first).
     *
     * @param $details
     * @return array
     */
    private function calculateTaxes($details)
    {
        // get the row prices
        $price = $details['price'] - $details['discount'];
        $shipping = $details['shipping'];
        $initialFee = $details['initial_fee'];

        // calculate individual taxes and sum them up
        $itemTax = $this->getCalculatedTax($price, $details);
        $shippingTax = $this->getCalculatedTax($shipping, $details);
        $initialFeeTax = $this->getCalculatedTax($initialFee, $details);
        $taxSum = $itemTax + $shippingTax + $initialFeeTax;

        // aggregate prices and calculate taxes
        $aggregatePrice = $price + $shipping + $initialFee;
        $aggregatePriceTax = $this->getCalculatedTax($aggregatePrice, $details);

        $taxes = [
            'item_tax' => $itemTax,
            'shipping_tax' => $shippingTax,
            'initial_fee_tax' => $initialFeeTax,
        ];

        // if sum of individual taxes is bigger than the aggregated prices tax, adjust the item price
        // we assume the item is the largest of the items
        if ($taxSum > $aggregatePriceTax) {
            $taxes['item_tax'] -= 0.01;
        }

        return $taxes;
    }
}