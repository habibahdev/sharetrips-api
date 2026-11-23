<?php

namespace App\Entity;

use App\Entity\Enum\Status\BookingStatus;
use App\Entity\Enum\Status\TripStatus;
use App\Entity\Traits\TimeTrait;
use App\Repository\TripRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TripRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['departure', 'destination', 'departure_at'])]
class Trip
{
    use TimeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $driver = null;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $departure = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $destination = null;

    #[ORM\Column]
    #[Assert\GreaterThan('now')]
    private ?\DateTimeImmutable $departureAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $estimatedArrivalAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private ?string $pricePerSeat = null;

    #[ORM\Column]
    #[Assert\Positive]
    private ?int $seatsOffered = null;

    #[ORM\Column(enumType: TripStatus::class)]
    private ?TripStatus $status = null;

    /**
     * @var Collection<int, TripStop>
     */
    #[ORM\OneToMany(targetEntity: TripStop::class, mappedBy: 'trip', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
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
    public function getSeatsAvailable(): int
    {
        $taken = 0;
        foreach ($this->bookings as $booking) {
            if (\in_array($booking->getStatus(), [BookingStatus::CONFIRMED, BookingStatus::COMPLETED], true)) {
                $taken += (int) $booking->getSeatsBooked();
            }
        }
        return max(0, (int) $this->seatsOffered - $taken);
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
