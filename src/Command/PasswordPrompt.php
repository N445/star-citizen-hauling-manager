<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Account\AccountRules;
use App\Service\Account\InvalidAccountException;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class PasswordPrompt
{
    private const int MAX_ATTEMPTS = 3;

    public function __construct(private AccountRules $rules)
    {
    }

    public function askNew(SymfonyStyle $io, string $label, bool $optional = false): ?string
    {
        $password = $io->askQuestion($this->hiddenQuestion($label, fn (?string $answer): ?string => $optional && null === $answer
            ? null
            : $this->rules->validPassword($answer)));

        if (null === $password) {
            return null;
        }

        $io->askQuestion($this->hiddenQuestion('Confirmation', static fn (?string $confirmation): string => $confirmation === $password
            ? $confirmation
            : throw new InvalidAccountException('Les mots de passe ne correspondent pas.')));

        return $password;
    }

    private function hiddenQuestion(string $label, callable $validator): Question
    {
        $question = new Question($label);
        $question->setHidden(true);
        $question->setValidator($validator);
        $question->setMaxAttempts(self::MAX_ATTEMPTS);

        return $question;
    }
}
