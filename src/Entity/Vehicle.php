<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Enum\Types\VehicleType;
use App\Entity\Traits\TimeTrait;
use App\Repository\VehicleRepository;
use App\State\VehicleProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VehicleRepository::class)]
#[ORM\HasLifecycleCallbacks()]
#[UniqueEntity(fields: ['licensePlate'], message: 'Cette plaque est déjà enregistrée.')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: VehicleProcessor::class
        ),
        new Get(security: "is_granted('ROLE_ADMIN') or object.getOwner() == user"),
        new Patch(security: "is_granted('ROLE_ADMIN') or object.getOwner() == user"),
    ],
    normalizationContext: ['groups' => ['vehicle:read']],
    denormalizationContext: ['groups' => ['vehicle:write']],
)]
class Vehicle
{
    use TimeTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['vehicle:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'vehicles')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['vehicle:read'])]
    private ?User $owner = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?string $brand = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?string $model = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?string $licensePlate = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?string $color = null;

    #[ORM\Column(enumType: VehicleType::class)]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?VehicleType $type = null;

    #[ORM\Column]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?bool $isActive = null;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 8)]
    #[Groups(['vehicle:read', 'vehicle:write'])]
    private ?int $seats = null;

    /**
     * @var Collection<int, Trip>
     */
    #[ORM\OneToMany(targetEntity: Trip::class, mappedBy: 'vehicle')]
    private Collection $trips;

    public function __construct()
    {
        $this->trips = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getLicensePlate(): ?string
    {
        return $this->licensePlate;
    }

    public function setLicensePlate(string $licensePlate): static
    {
        $this->licensePlate = $licensePlate;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getType(): ?VehicleType
    {
        return $this->type;
    }

    public function setType(VehicleType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSeats(): ?int
    {
        return $this->seats;
    }

    public function setSeats(int $seats): static
    {
        $this->seats = $seats;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, Trip>
     */
    public function getTrips(): Collection
    {
        return $this->trips;
    }

    public function addTrip(Trip $trip): static
    {
        if (!$this->trips->contains($trip)) {
            $this->trips->add($trip);
            $trip->setVehicle($this);
        }

        return $this;
    }

    public function removeTrip(Trip $trip): static
    {
        if ($this->trips->removeElement($trip)) {
            // set the owning side to null (unless already changed)
            if ($trip->getVehicle() === $this) {
                $trip->setVehicle(null);
            }
        }

        return $this;
    }
}
