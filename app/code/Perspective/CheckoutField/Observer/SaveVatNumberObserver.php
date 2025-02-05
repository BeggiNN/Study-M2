<?php

declare(strict_types=1);

namespace Perspective\CheckoutField\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;

class SaveVatNumberObserver implements ObserverInterface
{
    /**
     * @param QuoteRepository $quoteRepository
     */
    public function __construct(
        private readonly QuoteRepository $quoteRepository
    ) {
    }

    /**
     * @param EventObserver $observer
     * @return void
     * @throws NoSuchEntityException
     */
    public function execute(EventObserver $observer)
    {
        $order = $observer->getOrder();
        $quote = $this->quoteRepository->get($order->getQuoteId());
        $order->setVatNumber($quote->getVatNumber());
    }
}
