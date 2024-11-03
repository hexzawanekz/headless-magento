<?php
declare(strict_types=1);

namespace StripeIntegration\Payments\Test\Mftf\Helper;

use Facebook\WebDriver\WebDriverBy;
use Magento\FunctionalTestingFramework\Helper\Helper;
use Magento\FunctionalTestingFramework\Module\MagentoWebDriver;

class StripeHelper extends Helper
{
    /**
     * Magento randomly fails to render the email field (~10% of the time), so we reload the page and try again
     *
     * @param string $selector
     */
    public function waitForEmailField($selector): void {
        /** @var MagentoWebDriver $magentoWebDriver */
        $magentoWebDriver = $this->getModule('\Magento\FunctionalTestingFramework\Module\MagentoWebDriver');

        try
        {
            $magentoWebDriver->waitForElementVisible(WebDriverBy::cssSelector($selector));
        }
        catch (\Exception $e)
        {
            // Magento randomly fails to render the email field (~10% of the time), so we reload the page and try again
            $magentoWebDriver->reloadPage();
            $magentoWebDriver->waitForPageLoad(30);
            $magentoWebDriver->waitForElementVisible(WebDriverBy::cssSelector($selector));
        }

        // The shipping form may still be loading after the email field is visible
        $magentoWebDriver->waitForPageLoad(30);
    }

    public function clickWithoutWait($cssSelector = null, $xpathSelector = null): void {
        /** @var MagentoWebDriver $magentoWebDriver */
        $magentoWebDriver = $this->getModule('\Magento\FunctionalTestingFramework\Module\MagentoWebDriver');

        if ($cssSelector)
            $magentoWebDriver->click(WebDriverBy::cssSelector($cssSelector));
        else if ($xpathSelector)
            $magentoWebDriver->click(WebDriverBy::xpath($xpathSelector));
    }

    /**
     * The reason this method exists is because the <waitElementClickable> method in MFTF does not respect the "time" attribute.
     * This means that on the very first run, when the cache is empty, the test fails after 10 seconds of waiting, while more time is needed.
     */
    public function waitForAddToCart($cssSelector = null, $xpathSelector = null)
    {
        /** @var MagentoWebDriver $I */
        $I = $this->getModule('\Magento\FunctionalTestingFramework\Module\MagentoWebDriver');

        if ($cssSelector)
            $selector = WebDriverBy::cssSelector($cssSelector);
        else if ($xpathSelector)
            $selector = WebDriverBy::xpath($xpathSelector);
        else
            throw new \Exception("No selector provided");

        $I->waitForElementVisible($selector);
        $I->waitForElementClickable($selector, 60);
    }
}
