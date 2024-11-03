<?php

namespace StripeIntegration\Payments\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Serialize\SerializerInterface;

class Patch007StripePaymentMethods implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    private $serializer;

    /**
     * Constructor
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        SerializerInterface $serializer
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->serializer = $serializer;
    }
    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $connection = $this->moduleDataSetup->getConnection();

        $select = $connection->select()
            ->from(
                $this->moduleDataSetup->getTable('stripe_payment_methods'),
                ['order_id', 'payment_method_type', 'payment_method_card_data']
            );

        $stripePaymentMethods = $connection->fetchAll($select);

        foreach ($stripePaymentMethods as $paymentMethod)
        {
            $updateData = $cardData = [];

            if (!empty($paymentMethod['payment_method_card_data']))
            {
                try
                {
                    $cardData = $this->serializer->unserialize($paymentMethod['payment_method_card_data']);
                }
                catch (\Exception $e)
                {
                    $cardData = [];
                }
            }

            $searchablePaymentMethodType = $this->getSearchablePaymentMethodType($paymentMethod['payment_method_type'], $cardData);

            if (empty($searchablePaymentMethodType))
            {
                continue;
            }

            $updateData['stripe_payment_method_type'] = $searchablePaymentMethodType;

            $connection->update(
                $this->moduleDataSetup->getTable('sales_order'),
                $updateData,
                ['entity_id = ?' => $paymentMethod['order_id']]
            );

            $connection->update(
                $this->moduleDataSetup->getTable('sales_order_grid'),
                $updateData,
                ['entity_id = ?' => $paymentMethod['order_id']]
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }
    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        /**
         * This is dependency to another patch. Dependency should be applied first
         * One patch can have few dependencies
         * Patches do not have versions, so if in old approach with Install/Ugrade data scripts you used
         * versions, right now you need to point from patch with higher version to patch with lower version
         * But please, note, that some of your patches can be independent and can be installed in any sequence
         * So use dependencies only if this important for you
         */
        return [];
    }

    public function revert()
    {

    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        /**
         * This internal Magento method, that means that some patches with time can change their names,
         * but changing name should not affect installation process, that's why if we will change name of the patch
         * we will add alias here
         */
        return [];
    }

    private function getSearchablePaymentMethodType($paymentMethodType, $cardData)
    {
        $type = strtolower($this->getPaymentMethodName($paymentMethodType) ?? "");

        if (!empty($cardData['card_type']))
        {
            $type .= " " . strtolower($cardData['card_type']);
        }

        if (!empty($cardData['wallet']))
        {
            $wallet = explode("_", $cardData['wallet']);
            $wallet = implode(" ", $wallet);
            $type .= " " . $wallet;
        }

        return $type;
    }

    private function getPaymentMethodName($code)
    {
        $details = $this->getPaymentMethodDetails();

        if (isset($details[$code]))
            return $details[$code]['name'];

        return ucwords(str_replace("_", " ", $code));
    }

    private function getPaymentMethodDetails()
    {
        return [
            // APMs
            'acss_debit' => [
                'name' => "ACSS Direct Debit / Canadian PADs"
            ],
            'afterpay_clearpay' => [
                'name' => "Afterpay / Clearpay"
            ],
            'alipay' => [
                'name' => "Alipay"
            ],
            'bacs_debit' => [
                'name' => "BACS Direct Debit"
            ],
            'au_becs_debit' => [
                'name' => "BECS Direct Debit"
            ],
            'bancontact' => [
                'name' => "Bancontact"
            ],
            'boleto' => [
                'name' => "Boleto"
            ],
            'customer_balance' => [
                'name' => "Bank transfer"
            ],
            'eps' => [
                'name' => 'EPS'
            ],
            'fpx' => [
                'name' => "FPX"
            ],
            'giropay' => [
                'name' => "Giropay"
            ],
            'grabpay' => [
                'name' => "GrabPay"
            ],
            'ideal' => [
                'name' => "iDEAL"
            ],
            'klarna' => [
                'name' => "Klarna"
            ],
            'konbini' => [
                'name' => "Konbini"
            ],
            'paypal' => [
                'name' => ""
            ],
            'multibanco' => [
                'name' => "Multibanco"
            ],
            'p24' => [
                'name' => "P24"
            ],
            'sepa_debit' => [
                'name' => "SEPA Direct Debit"
            ],
            'sepa_credit' => [
                'name' => "SEPA Credit Transfer"
            ],
            'sofort' => [
                'name' => "SOFORT"
            ],
            'wechat' => [
                'name' => "WeChat Pay"
            ],
            'ach_debit' => [
                'name' => "ACH Direct Debit"
            ],
            'us_bank_account' => [ // ACHv2
                'name' => "ACH Direct Debit"
            ],
            'oxxo' => [
                'name' => "OXXO"
            ],
            'paynow' => [
                'name' => "PayNow"
            ],
            'mobilepay' => [
                'name' => "MobilePay"
            ],
            'link' => [
                'name' => 'Link'
            ],
            'bank' => [
                'name' => ""
            ],
            'google_pay' => [
                'name' => "Google Pay"
            ],
            'apple_pay' => [
                'name' => "Apple Pay"
            ],

            // Cards
            'amex' => [
                'name' => "American Express"
            ],
            'cartes_bancaires' => [
                'name' => "Cartes Bancaires"
            ],
            'diners' => [
                'name' => "Diners Club"
            ],
            'discover' => [
                'name' => "Discover"
            ],
            'generic' => [
                'name' => ""
            ],
            'jcb' => [
                'name' => "JCB"
            ],
            'mastercard' => [
                'name' => "MasterCard"
            ],
            'visa' => [
                'name' => "Visa"
            ],
            'unionpay' => [
                'name' => "UnionPay"
            ]
        ];
    }
}