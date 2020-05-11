<?php
declare(strict_types=1);

namespace Flight\Api\Controller;

use Flight\Application\Ticket\Purchase;
use Flight\Application\Ticket\Refund;
use Shared\HttpFoundation\Result;
use Shared\HttpFoundation\Success;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @Route("/api/v1/tickets")
 */
class TicketController
{
    private MessageBusInterface $commandBus;
    private ValidatorInterface $validator;
    private LockFactory $lockFactory;

    public function __construct(
        MessageBusInterface $commandBus,
        ValidatorInterface $validator,
        LockFactory $lockFactory
    )
    {
        $this->commandBus = $commandBus;
        $this->validator = $validator;
        $this->lockFactory = $lockFactory;
    }

    /**
     * @Route("/purchase", methods={"POST"})
     * @param Purchase $command
     *
     * @return Result
     */
    public function purchase(Purchase $command): Result
    {
        $lock = $this->lockFactory->createLock($command->flightId . $command->seat);
        if ($lock->acquire()) {
            $errors = $this->validator->validate($command);
            if (count($errors) > 0) {
                var_dump((string) $errors);exit;
            }
            $this->commandBus->dispatch($command);
            $lock->release();
        }

        return new Success($command);
    }

    /**
     * @Route("/refund/{ticketId}", methods={"POST"})
     * @param string $ticketId
     *
     * @return Result
     */
    public function refund(string $ticketId): Result
    {
        $lock = $this->lockFactory->createLock('refund' . $ticketId);
        if ($lock->acquire()) {
            $command = new Refund($ticketId);
            $errors = $this->validator->validate($command);
            if (count($errors) > 0) {
                var_dump((string) $errors);exit;
            }
            $this->commandBus->dispatch($command);
            $lock->release();
        }

        return new Success($command);
    }
}
