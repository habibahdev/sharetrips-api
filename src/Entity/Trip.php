<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Enum\Status\BookingStatus;
use App\Entity\Enum\Status\TripStatus;
use App\Entity\Traits\TimeTrait;
use App\Repository\TripRepository;
use App\State\TripProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TripRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['departure', 'destination', 'departure_at'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            denormalizationContext: ['groups' => ['trip:write', 'trip:create']],
            validationContext: ['groups' => ['Default', 'trip:create']],
            processor: TripProcessor::class
        ),
        new Patch(
            denormalizationContext: ['groups' => ['trip:write']],
            security: "is_granted('ROLE_ADMIN') or object.getDriver() == user",
            processor: TripProcessor::class
        ),
    ],
    normalizationContext: ['groups' => ['trip:read', 'user:public', 'vehicle:public']],
    order: ['departureAt' => 'ASC'],
)]
class Trip
{
    use TimeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['trip:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['trip:read'])]
    private ?User $driver = null;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(groups: ['trip:create'])]
    #[Groups(['trip:read', 'trip:create'])]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['trip:read', 'trip:write'])]
    private ?string $departure = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $destination = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\GreaterThan('now', groups: ['trip:create'])]
    #[Groups(['trip:read', 'trip:write'])]
    private ?\DateTimeImmutable $departureAt = null;

    #[ORM\Column(nullable: true)]
    #[Assert\GreaterThan(propertyPath: 'departureAt', message: "L'arrivée doit être postérieure au départ.")]
    #[Groups(['trip:read', 'trip:write'])]
    private ?\DateTimeImmutable $estimatedArrivalAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    #[Assert\NotNull]
    #[Groups(['trip:read', 'trip:write'])]
    private ?string $pricePerSeat = null;

    #[ORM\Column]
    #[Assert\Positive]
    #[Assert\NotNull]
    #[Groups(['trip:read', 'trip:write'])]
    private ?int $seatsOffered = null;

    #[ORM\Column(enumType: TripStatus::class)]
    #[Groups(['trip:read', 'trip:write'])]
    private ?TripStatus $status = null;

    /**
     * @var Collection<int, TripStop>
     */
    #[ORM\OneToMany(targetEntity: TripStop::class, mappedBy: 'trip', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Assert\Valid]
    #[Groups(['trip:read', 'trip:write'])]
    private Collection $stops;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'trip')]
    private Collection $bookings;

    public function __construct()
    {
        $this->stops = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->status= TripStatus::PUBLISHED;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDriver(): ?User
    {
        return $this->driver;
    }

    public function setDriver(?User $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    public function getDeparture(): ?string
    {
        return $this->departure;
    }

    public function setDeparture(string $departure): static
    {
        $this->departure = $departure;

        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(string $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    public function getDepartureAt(): ?\DateTimeImmutable
    {
        return $this->departureAt;
    }

    public function setDepartureAt(\DateTimeImmutable $departureAt): static
    {
        $this->departureAt = $departureAt;

        return $this;
    }

    public function getEstimatedArrivalAt(): ?\DateTimeImmutable
    {
        return $this->estimatedArrivalAt;
    }

    public function setEstimatedArrivalAt(?\DateTimeImmutable $estimatedArrivalAt): static
    {
        $this->estimatedArrivalAt = $estimatedArrivalAt;

        return $this;
    }

    public function getPricePerSeat(): ?string
    {
        return $this->pricePerSeat;
    }

    public function setPricePerSeat(string $pricePerSeat): static
    {
        $this->pricePerSeat = $pricePerSeat;

        return $this;
    }

    public function getSeatsOffered(): ?int
    {
        return $this->seatsOffered;
    }

    public function setSeatsOffered(int $seatsOffered): static
    {
        $this->seatsOffered = $seatsOffered;

        return $this;
    }

    public function getStatus(): ?TripStatus
    {
        return $this->status;
    }

    public function setStatus(TripStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Place encore disponibles
     * @return int
     */
    public function getSeatsBooked(): int
    {
        $taken = 0;
        foreach ($this->bookings as $booking) {
            if (\in_array($booking->getStatus(), [BookingStatus::CONFIRMED, BookingStatus::COMPLETED], true)) {
                $taken += (int) $booking->getSeatsBooked();
            }
        }
        return $taken;
    }

    #[Groups(['trip:read'])]
    public function getSeatsAvailable(): int
    {
        return max(0, (int) $this->seatsOffered - $this->getSeatsBooked());
    }

    /**
     * @return Collection<int, TripStop>
     */
    public function getStops(): Collection
    {
        return $this->stops;
    }

    public function addStop(TripStop $stop): static
    {
        if (!$this->stops->contains($stop)) {
            $this->stops->add($stop);
            $stop->setTrip($this);
        }

        return $this;
    }

    public function removeStop(TripStop $stop): static
    {
        if ($this->stops->removeElement($stop)) {
            // set the owning side to null (unless already changed)
            if ($stop->getTrip() === $this) {
                $stop->setTrip(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setTrip($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getTrip() === $this) {
                $booking->setTrip(null);
            }
        }

        return $this;
    }
}
