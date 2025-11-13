<?php

declare(strict_types=1);

namespace Perspective\CheckoutField\Plugin\Checkout\Model;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\GuestShippingInformationManagement;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;

class GuestShippingInformationManagementPlugin
{
    /**
     * @param QuoteRepository $quoteRepository
     */
    public function __construct(
        private readonly QuoteRepository $quoteRepository
    ) {
    }

    /**
     * Save VAT number before saving guest shipping address information
     *
     * @param GuestShippingInformationManagement $subject
     * @param string $cartId
     * @param ShippingInformationInterface $addressInformation
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSaveAddressInformation(
        GuestShippingInformationManagement $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ): void {
        $vatNumber = $addressInformation->getExtensionAttributes()->getVatNumber();
        $quote = $this->quoteRepository->getActive($cartId);
        $quote->setVatNumber($vatNumber);
    }
}
