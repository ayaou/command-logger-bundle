<?php

namespace Ayaou\CommandLoggerBundle\Entity;

use Ayaou\CommandLoggerBundle\Repository\CommandLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandLogRepository::class)]
#[ORM\Table(name: 'command_log')]
#[ORM\Index(fields: ['commandName'])]
#[ORM\Index(fields: ['startTime'])]
#[ORM\Index(fields: ['commandName', 'startTime'])]
class CommandLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $commandName = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $arguments = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endTime = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $exitCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private ?string $executionToken = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommandName(): ?string
    {
        return $this->commandName;
    }

    public function setCommandName(string $commandName): self
    {
        $this->commandName = $commandName;

        return $this;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setArguments(array $arguments = []): self
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeImmutable $startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeImmutable $endTime): self
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    public function setExitCode(?int $exitCode): self
    {
        $this->exitCode = $exitCode;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getExecutionToken(): ?string
    {
        return $this->executionToken;
    }

    public function setExecutionToken(string $executionToken): self
    {
        $this->executionToken = $executionToken;

        return $this;
    }
}
