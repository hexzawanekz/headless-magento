# How to integrate a custom fee to the tax calculation

## About

While calculating tax you may need to add additional fees to the products which you are selling and those fees 
will need to have taxes calculated for them.

The Stripe Tax module creates different requests at the point of order, invoice, and credit memo creation to send 
forward to the Stripe Tax calculation [API](https://docs.stripe.com/api/tax/calculations). The module runs through the 
items of the quote, invoice or credit memo to add the details of the items or the entire order to the calculation 
request.

If you add a custom fee which will need to have the tax calculated for it, you will need to provide the details of that
fee to the Stripe Tax module so that it can be added to the tax calculation request.

The principle of the integration is for the developer of the custom fee to provide the total value of the custom fee
through an Observer, which then will be added to the Tax calculation API request and sent to Stripe to be calculated.

# Details

The way the Stripe Tax module works for adding the additional fees is that we take into consideration two different
places where these fees occur:
1. At item level (the tax is applied to each item of the quote / invoice / credit memo)
2. At quote / order / invoice / credit memo level (the tax is set for the whole basket and further)

In order to have this work, we need to have 3 different details sent from the 3rd party developer:
1. The total price of the custom fee in the store display currency (if an item has a custom tax of 3, and the qty of 
the item is 2, then the value sent will be 6)
2. The tax class for that custom fee
3. The code of that custom fee

These three components will be sent forward in an array with the following structure:

```php
$details = [
    'amount' => $amountValue, // generic value, please provide your own when developing
    'tax_class_id' => $classId, // generic value, please provide your own when developing
    'code' => 'custom_fee' // generic value, please provide your own when developing
];
```

This array structure will need to be sent based on what action is being done (quote calculation, invoice creation or 
credit memo creation) and where does custom fee apply (item level or quote / invoice / credit memo level).

The way this information will be sent to the Tax module is via an Observer which provides an object to which the 
information will be added and different other properties to use for providing the correct values. The observer will 
have to listen to one of the following 6 events depending on where you are in the order process and where the custom
fee will be applied: 
1. `stripe_tax_additional_fee_item` for when you have an additional fee that applies to the items of an order
2. `stripe_tax_additional_fee_quote` for when you have an additional fee that applies to the quote
3. `stripe_tax_additional_fee_invoice_item` which applies to invoice items
4. `stripe_tax_additional_fee_invoice` which applies to the whole invoice
5. `stripe_tax_additional_fee_creditmemo_item` which applies to credit memo items
6. `stripe_tax_additional_fee_creditmemo` which applies to the whole credit memo

Each of the events will contain a Magento DataObject called `additional_fees_container` to which the developer will
add the details to be calculated by the Stripe Tax module by calling the method `->addAdditionalFee()` with the
details array as a parameter:
```php
$additionalFees = $observer->getAdditionalFeesContainer();

// other operations to get the values which will be sent forward

$details = [
    'amount' => $amountValue, // generic value, please provide your own when developing
    'tax_class_id' => $classId, // generic value, please provide your own when developing
    'code' => 'custom_fee' // generic value, please provide your own when developing
];

$additionalFees->addAdditionalFee($details);
}
```

**_NOTE 1:_** For cases when these additional fees might be added either on parent or on children items, cases like a
bundled dynamic product, we send events for both the bundle product and the products within it.

**_NOTE 2:_** When using bundled products or other type of products where you can specify the quantity of the parent and
child product separately, make sure that you send the amount of the additional fee taking into consideration both the 
qty of the parent and the child items.

The other details that will be provided for the events will be detailed in the examples for each of the events.

After the taxes are calculated by Stripe for the invoices, you will receive back an array of the tax and the base tax. 
This is so you can set custom fields in  the DB if you require it. The array will have the fee code as a key and the 
calculated values set for it. It can be accessed with the `getAdditionalFeesTax()` method called in the item or on the invoice.

### Example for tax applying on the item at quote level

Inside your events file `app/code/Vendor/YourModule/etc/events.xml` add the following event:
```xml
<event name="stripe_tax_additional_fee_item">
    <observer name="your_custom_observer_name" instance="Vendor\YourModule\Observer\AddAdditionalFeeForQuoteItem" />
</event>
```
The data provided to the event apart from the `additional_fees_container` is:
1. `item` the item for which the tax can be calculated
2. `quote` the quote to which the item belongs to

Inside your observer file `app/code/Vendor/YourModule/Observer/AddAdditionalFeeForQuoteItem.php` add the code for 
creating the details for the calculation. An example would be as follows:
```php
<?php

namespace Vendor\YourModule\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;

class AddAdditionalFeeForQuoteItem implements ObserverInterface
{

    public function execute(Observer $observer)
    {
        $additionalFees = $observer->getAdditionalFeesContainer();
        $item = $observer->getItem();
        $quote = $observer->getQuote();

        // Calculations where you determine that the item has an additional tax and the tax needs to be calculated
        // after the calculations are done and you have the values, add them to the details array and sent the array forward
        
        $itemDetails = [
                'amount' => $amount, // generic value determined from previous calculations, please provide your own when developing
                'tax_class_id' => $taxClassId, // generic value determined from previous calculations, please provide your own when developing
                'code' => 'custom_fee' // generic value, please provide your own when developing
            ];

        $additionalFees->addAdditionalFee($itemDetails);
    }
}
```

### Example for tax applying at quote level

Inside your events file `app/code/Vendor/YourModule/etc/events.xml` add the following event:
```xml
<event name="stripe_tax_additional_fee_quote">
    <observer name="stripe_tax_additional_fee_quote" instance="Vendor\YourModule\Observer\AddAdditionalFeeForQuote" />
</event>
```
The data provided to the event apart from the `additional_fees_container` is:
1. `quote` The quote on which the tax should be calculated
2. `total` the collected totals up to this point

Inside your observer file `app/code/Vendor/YourModule/Observer/AddAdditionalFeeForQuote.php` add the code for
creating the details for the calculation. An example would be as follows:
```php
<?php

namespace Vendor\YourModule\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;

class AddAdditionalFeeForQuote implements ObserverInterface
{

    public function execute(Observer $observer)
    {
        $additionalFees = $observer->getAdditionalFeesContainer();
        $quote = $observer->getQuote();
        $total = $observer->getTotal();

        // Calculations where you determine that the quote has an additional tax and the tax needs to be calculated
        // after the calculations are done and you have the values, add them to the details array and sent the array forward
        
        $details = [
                'amount' => $amount, // generic value determined from previous calculations, please provide your own when developing
                'tax_class_id' => $taxClassId, // generic value determined from previous calculations, please provide your own when developing
                'code' => 'custom_fee' // generic value, please provide your own when developing
            ];

        $additionalFees->addAdditionalFee($details);
    }
}
```

### Example for tax applying on the item at invoice level

Inside your events file `app/code/Vendor/YourModule/etc/events.xml` add the following event:
```xml
<event name="stripe_tax_additional_fee_invoice_item">
    <observer name="your_custom_observer_name" instance="Vendor\YourModule\Observer\AddAdditionalFeeForInvoiceItem" />
</event>
```
The data provided to the event apart from the `additional_fees_container` is:
1. `item` the item for which the tax can be calculated; you can get other details like the order item for this item once in the observer
2. `invoice` the quote to which the item belongs to; from the invoice you can get such things as the order of the item

Inside your observer file `app/code/Vendor/YourModule/Observer/AddAdditionalFeeForInvoiceItem.php` add the code for
creating the details for the calculation. An example would be as follows:
```php
<?php

namespace Vendor\YourModule\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;

class AddAdditionalFeeForInvoiceItem implements ObserverInterface
{

    public function execute(Observer $observer)
    {
        $additionalFees = $observer->getAdditionalFeesContainer();
        $item = $observer->getItem();
        $invoice = $observer->getInvoice();

        // Calculations where you determine that the item has an additional tax and the tax needs to be calculated
        // after the calculations are done and you have the values, add them to the details array and sent the array forward
        
        $itemDetails = [
                'amount' => $amount, // generic value determined from previous calculations, please provide your own when developing
                'tax_class_id' => $taxClassId, // generic value determined from previous calculations, please provide your own when developing
                'code' => 'custom_fee' // generic value, please provide your own when developing
            ];

        $additionalFees->addAdditionalFee($itemDetails);
    }
}
```

### Example for tax applying at invoice level

Inside your events file `app/code/Vendor/YourModule/etc/events.xml` add the following event:
```xml
<event name="stripe_tax_additional_fee_invoice">
    <observer name="stripe_tax_additional_fee_quote" instance="Vendor\YourModule\Observer\AddAdditionalFeeForInvoice" />
</event>
```
The data provided to the event apart from the `additional_fees_container` is:
1. `invoice` The invoice on which the custom fee is applied
2. `order` The order to which the invoice belongs to

Inside your observer file `app/code/Vendor/YourModule/Observer/AddAdditionalFeeForInvoice.php` add the code for
creating the details for the calculation. An example would be as follows:
```php
<?php

namespace Vendor\YourModule\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;

class AddAdditionalFeeForInvoice implements ObserverInterface
{

    public function execute(Observer $observer)
    {
        $additionalFees = $observer->getAdditionalFeesContainer();
        $invoice = $observer->getInvoice();
        $order = $observer->getOrder();

        // Calculations where you determine that the invoice has an additional tax and the tax needs to be calculated
        // after the calculations are done and you have the values, add them to the details array and sent the array forward
        
        $details = [
                'amount' => $amount, // generic value determined from previous calculations, please provide your own when developing
                'tax_class_id' => $taxClassId, // generic value determined from previous calculations, please provide your own when developing
                'code' => 'custom_fee' // generic value, please provide your own when developing
            ];

        $additionalFees->addAdditionalFee($details);
    }
}
```

### Changes in the case of credit memos

When creating credit memos, the structure of the array which needs to be passed back to the Stripe Tax Module changes.
The `tax_class_id` is taken out of it and you will get a nef field called `amount_tax` which must contain the tax amount
which you want to be refunded for the custom fee.
```php
$details = [
    'amount' => $amount,
    'amount_tax' => $taxAmount,
    'code' => 'custom_fee'
];
```
**_NOTE:_** One important thing to bear in mind is that for the `code` component of the details array, it needs to be the same code 
as the one provided to the invoice step. This is in order for Stripe to know what component to subtract the refunded 
amounts from.

### Example for tax applying on the item at credit memo level

Inside your events file `app/code/Vendor/YourModule/etc/events.xml` add the following event:
```xml
<event name="stripe_tax_additional_fee_creditmemo_item">
    <observer name="your_custom_observer_name" instance="Vendor\YourModule\Observer\AddAdditionalFeeForCreditmemoItem" />
</event>
```
The data provided to the event apart from the `additional_fees_container` is:
1. `item` the item for which the tax can be calculated; you can get other details like the order item for this item once in the observer
2. `creditmemo` the quote to which the item belongs to; 
3. `invoice` the quote to which the item belongs to; 
4. `order` the quote to which the item belongs to; 

Inside your observer file `app/code/Vendor/YourModule/Observer/AddAdditionalFeeForCreditmemoItem.php` add the code for
creating the details for the calculation. An example would be as follows:
```php
<?php

namespace Vendor\YourModule\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;

class AddAdditionalFeeForCreditmemoItem implements ObserverInterface
{

    public function execute(Observer $observer)
    {
        $additionalFees = $observer->getAdditionalFeesContainer();
        $item = $observer->getItem();
        $creditmemo = $observer->getCreditmemo();
        $invoice = $observer->getInvoice();
        $order = $observer->getOrder();

        // Calculations where you determine that the item has an additional tax and the tax needs to be refunded
        // after the calculations are done and you have the values, add them to the details array and sent the array forward
        
        $itemDetails = [
                'amount' => $amount, // generic value determined from previous calculations, please provide your own when developing
                'tax_amount' => $taxAmount, // generic value determined from previous calculations, please provide your own when developing
                'code' => 'custom_fee' // generic value, please provide your own when developing
            ];

        $additionalFees->addAdditionalFee($itemDetails);
    }
}
```

### Example for tax applying at credit memo level

Inside your events file `app/code/Vendor/YourModule/etc/events.xml` add the following event:
```xml
<event name="stripe_tax_additional_fee_creditmemo">
    <observer name="stripe_tax_additional_fee_quote" instance="Vendor\YourModule\Observer\AddAdditionalFeeForCreditmemo" />
</event>
```
The data provided to the event apart from the `additional_fees_container` is:
1. `creditmemo` the quote to which the item belongs to;
2. `invoice` the quote to which the item belongs to;
3. `order` the quote to which the item belongs to;

Inside your observer file `app/code/Vendor/YourModule/Observer/AddAdditionalFeeForCreditmemo.php` add the code for
creating the details for the calculation. An example would be as follows:
```php
<?php

namespace Vendor\YourModule\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;

class AddAdditionalFeeForInvoice implements ObserverInterface
{

    public function execute(Observer $observer)
    {
        $additionalFees = $observer->getAdditionalFeesContainer();
        $creditmemo = $observer->getCreditmemo();
        $invoice = $observer->getInvoice();
        $order = $observer->getOrder();

        // Calculations where you determine that the invoice has an additional tax and the tax needs to be refunded
        // after the calculations are done and you have the values, add them to the details array and sent the array forward
        
        $details = [
                'amount' => $amount, // generic value determined from previous calculations, please provide your own when developing
                'tax_amount' => $taxClassId, // generic value determined from previous calculations, please provide your own when developing
                'code' => 'custom_fee' // generic value, please provide your own when developing
            ];

        $additionalFees->addAdditionalFee($details);
    }
}
```