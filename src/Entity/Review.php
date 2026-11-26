<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Traits\TimeTrait;
use App\Repository\ReviewRepository;
use App\State\ReviewProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(columns: ['booking_id', 'author_id'])]
#[ORM\Index(columns: ['reviewed_user_id'])]
#[ApiResource(
    operations: [
        new GetCollection(
            parameters: [
                'author' => new QueryParameter(
                    filter: new ExactFilter(),
                    property: 'author',
                    description: 'Filtrage les avis selon leur auteur.'
                ),
                'booking' => new QueryParameter(
                    filter: new ExactFilter(),
                    property: 'booking',
                    description: 'Filtrage les avis selon leur réservation.'
                ),
                'rating' => new QueryParameter(
                    filter: new ExactFilter(),
                    property: 'rating',
                    description: 'Filtrage les avis selon leur note.'
                ),
            ]
        ),
        new GetCollection(
            uriTemplate: '/users/{userId}/reviews',
            uriVariables: ['userId' => new Link(toProperty: 'reviewedUser', fromClass: User::class)],
            parameters: [
                'rating' => new QueryParameter(
                    filter: new ExactFilter(),
                    property: 'rating',
                    description: 'Filtrage les avis reçus selon leur note.'
                )
            ]
        ),
        new Get(),
        new Post(
            denormalizationContext: ['groups' => ['review:write', 'review:create']],
            security: "is_granted('ROLE_USER')",
            processor: ReviewProcessor::class
        ),
        new Patch(
            denormalizationContext: ['groups' => ['review:write']],
            security: "is_granted('ROLE_ADMIN') or object.getAuthor() == user",
            processor: ReviewProcessor::class
        ),
    ],
    normalizationContext: ['groups' => ['review:read', 'user:public', 'timestamp:read']],
    order: ['createdAt' => 'DESC'],
)]
class Review
{
    use TimeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['review:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'review')]
    #[ORM\JoinColumn(unique: true, nullable: false)]
    #[Assert\NotNull]
    #[Groups(['review:read', 'review:create'])]
    private ?Booking $booking = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['review:read'])]
    private ?User $author = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['review:read'])]
    private ?User $reviewedUser = null;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 5)]
    #[Assert\NotNull]
    #[Groups(['review:read', 'review:write'])]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    #[Groups(['review:read', 'review:write'])]
    private ?string $comment = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(Booking $booking): static
    {
        $this->booking = $booking;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getReviewedUser(): ?User
    {
        return $this->reviewedUser;
    }

    public function setReviewedUser(?User $reviewedUser): static
    {
        $this->reviewedUser = $reviewedUser;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
