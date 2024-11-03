<?php

namespace StripeIntegration\Tax\Logger;

use Monolog\Logger;
use \Magento\Framework\Logger\Handler\Base;

class Handler extends Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::DEBUG;
    public $filePath;

    public function __construct(
        \Magento\Framework\Filesystem\DriverInterface $filesystem,
        \Magento\Framework\App\Filesystem\DirectoryList $dir
    ) {
        $this->filePath = $dir->getPath('log') . DIRECTORY_SEPARATOR . 'stripe_tax.log';
        parent::__construct($filesystem, $this->filePath);
    }
}
