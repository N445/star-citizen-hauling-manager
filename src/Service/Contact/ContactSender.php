<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\ContactCategory;
use App\Entity\ContactMessage;
use App\Entity\User;
use App\Repository\ContactMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Enregistre un message de contact. Anti-abus (en plus de Turnstile) : un délai minimal entre deux messages et un plafond
 * par heure, par compte ou par jeton invité.
 */
final readonly class ContactSender
{
    public const int MAX_MESSAGE_LENGTH = 4000;

    public const int MIN_SECONDS_BETWEEN = 60;

    public const int MAX_PER_HOUR = 5;

    public function __construct(
        private ContactMessageRepository $messages,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ContactException
     */
    public function send(ContactCategory $category, string $message, ?string $replyEmail, ?User $author, string $guestToken, string $locale): ContactMessage
    {
        $now = $this->clock->now();
        $this->assertNotTooFrequent($author, $guestToken, $now);
        $replyEmail = mb_strtolower(trim((string) $replyEmail));

        $contact = new ContactMessage($author, null === $author ? $guestToken : null, '' === $replyEmail ? null : $replyEmail, $category, trim($message), $locale, $now);
        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        return $contact;
    }

    private function assertNotTooFrequent(?User $author, string $guestToken, \DateTimeImmutable $now): void
    {
        $recent = $this->messages->datesSince($author, $guestToken, $now->modify('-1 hour'));

        if ([] === $recent) {
            return;
        }

        if (\count($recent) >= self::MAX_PER_HOUR) {
            throw new ContactException('contact.error.too_many', ['maximum' => self::MAX_PER_HOUR]);
        }

        $wait = self::MIN_SECONDS_BETWEEN - ($now->getTimestamp() - $recent[0]->getTimestamp());

        if ($wait > 0) {
            throw new ContactException('contact.error.too_soon', ['seconds' => $wait]);
        }
    }
}
