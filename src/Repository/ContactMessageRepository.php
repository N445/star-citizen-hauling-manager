<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContactMessage;
use App\Entity\ReportStatus;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactMessage>
 */
final class ContactMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactMessage::class);
    }

    /**
     * @return list<\DateTimeImmutable> dates des messages de cet auteur depuis `$since`, du plus récent au plus ancien
     */
    public function datesSince(?User $author, string $guestToken, \DateTimeImmutable $since): array
    {
        $query = $this->createQueryBuilder('contact')
            ->where('contact.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('contact.createdAt', 'DESC');

        if (null !== $author) {
            $query->andWhere('contact.author = :author')->setParameter('author', $author);
        } else {
            $query->andWhere('contact.author IS NULL AND contact.guestToken = :token')->setParameter('token', $guestToken);
        }

        return array_map(static fn (ContactMessage $contact): \DateTimeImmutable => $contact->createdAt(), $query->getQuery()->getResult());
    }

    /**
     * Le compte disparaît, ses messages restent pour l'admin : plus d'auteur ni d'e-mail de réponse.
     */
    public function anonymizeAuthor(User $author): void
    {
        $this->getEntityManager()->createQueryBuilder()
            ->update(ContactMessage::class, 'contact')
            ->set('contact.author', ':nobody')
            ->set('contact.replyEmail', ':nobody')
            ->where('contact.author = :author')
            ->setParameter('nobody', null)
            ->setParameter('author', $author)
            ->getQuery()
            ->execute();
    }

    public function countOpen(): int
    {
        return $this->count(['status' => ReportStatus::Open]);
    }

    /**
     * @return list<ContactMessage>
     */
    public function latest(int $limit): array
    {
        return $this->findBy([], ['id' => 'DESC'], $limit);
    }
}
