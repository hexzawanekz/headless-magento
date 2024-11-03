<?php

namespace StripeIntegration\Tax\Controller\Adminhtml\Tax;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\Exception\NotFoundException;

class Classes implements ActionInterface
{
    const ADMIN_RESOURCE = 'StripeIntegration_Tax::stripe_tax_classes';

    private $serializer;
    private $auth;
    private $resultPageFactory;
    private $request;
    private $messageManager;
    private $resultRedirectFactory;
    private $taxClassCollection;
    private $taxClassRepository;
    private $taxClassFactory;
    private $logger;

    public function __construct(
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory,
        \Magento\Tax\Model\ResourceModel\TaxClass\Collection $taxClassCollection,
        \Magento\Tax\Api\TaxClassRepositoryInterface $taxClassRepository,
        \Magento\Tax\Model\ClassModelFactory $taxClassFactory,
        \StripeIntegration\Tax\Helper\Logger $logger
    ) {
        $this->serializer = $serializer;
        $this->auth = $context->getAuth();
        $this->resultPageFactory = $resultPageFactory;
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->taxClassCollection = $taxClassCollection;
        $this->taxClassRepository = $taxClassRepository;
        $this->taxClassFactory = $taxClassFactory;
        $this->logger = $logger;
    }

    public function execute()
    {
        if (!$this->auth->isLoggedIn()) {
            return $this->redirect('admin');
        }

        $isPost = $this->request->isPost();

        if ($isPost)
        {
            $data = $this->request->getPostValue("tax_classes");

            try
            {
                $this->saveData($data);
            }
            catch (\Exception $e)
            {
                $this->messageManager->addErrorMessage($e->getMessage());
            }

            // Redirect to the same page as a GET request
            return $this->redirect('*/*/*/');
        }

        return $this->getResultPage();
    }

    private function redirect($path)
    {
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath($path);
        return $redirect;
    }

    private function saveData($data)
    {
        if (empty($data))
        {
            throw new NotFoundException(__("No data was received."));
        }

        // Unserialize the data
        $taxClasses = $this->serializer->unserialize($data);

        if (empty($taxClasses))
        {
            return $this->deleteAllTaxClasses();
        }
        else
        {
            $this->deleteTaxClassesNotIn($taxClasses);
        }

        // Upsert each tax class
        $failedUpserts = 0;
        $successfullUpserts = 0;
        foreach ($taxClasses as $taxClass)
        {
            try
            {
                $this->upsertTaxClass($taxClass);
                $successfullUpserts++;
            }
            catch (\Exception $e)
            {
                $failedUpserts++;
                $this->logger->logError("Failed to save tax class: " . $e->getMessage());
            }
        }

        if ($failedUpserts > 0)
        {
            $this->messageManager->addErrorMessage(
                __('Failed to save %1 tax classes.', $failedUpserts)
            );

            if ($successfullUpserts > 0)
            {
                $this->messageManager->addSuccessMessage(
                    __('%1 tax classes have been saved.', $successfullUpserts)
                );
            }
        }
        else
        {
            $this->messageManager->addSuccessMessage(__('Tax classes have been saved.'));
        }
    }

    private function deleteAllTaxClasses()
    {
        $this->taxClassCollection->walk('delete');
        $this->messageManager->addSuccessMessage(__('Tax classes have been saved.'));
    }

    private function upsertTaxClass($taxClass)
    {
        if (empty($taxClass))
        {
            throw new NotFoundException(__("No tax class data was received."));
        }

        if (empty($taxClass['class_name']) || empty(trim($taxClass['class_name'])))
        {
            throw new NotFoundException(__("Tax class name is required."));
        }

        if (empty($taxClass['class_id'])) {
            $taxClassModel = $this->taxClassFactory->create();
        } else {
            $taxClassModel = $this->taxClassRepository->get($taxClass['class_id']);
        }

        $taxClassModel->setClassName($taxClass['class_name']);
        $taxClassModel->setClassType(\Magento\Tax\Model\ClassModel::TAX_CLASS_TYPE_PRODUCT);
        $taxClassModel->setStripeProductTaxCode($taxClass['stripe_product_tax_code']);
        $taxClassModel->setStripeProductTaxCodeName($taxClass['stripe_product_tax_code_name']);
        $this->taxClassRepository->save($taxClassModel);
    }

    private function deleteTaxClassesNotIn($taxClasses)
    {
        $taxClassIds = array_column($taxClasses, 'class_id');
        $taxClassesToDelete = $this->taxClassCollection->addFieldToFilter('class_id', ['nin' => $taxClassIds]);
        $taxClassesToDelete->addFieldToFilter('class_type', \Magento\Tax\Model\ClassModel::TAX_CLASS_TYPE_PRODUCT);

        foreach ($taxClassesToDelete as $taxClass)
        {
            $taxClass->delete();
        }
    }

    private function getResultPage()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('StripeIntegration_Tax::stripe_tax_classes');
        $resultPage->getConfig()->getTitle()->prepend(__('Stripe Tax Classes'));

        // Trigger the javascript event "saveChanges" when the Save Changes button is clicked
        $onclickAction = "const saveChanges = new Event('saveChanges'); document.dispatchEvent(saveChanges); return false;";
        $resultPage->getLayout()->getBlock('page.actions.toolbar')->addChild(
            'submit_form_button',
            \Magento\Backend\Block\Widget\Button::class,
            [
                'label' => __('Save Changes'),
                'onclick' => $onclickAction,
                'class' => 'add primary'
            ]
        );

        return $resultPage;
    }
}
