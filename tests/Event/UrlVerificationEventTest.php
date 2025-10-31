<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Event\UrlVerificationEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(UrlVerificationEvent::class)]
final class UrlVerificationEventTest extends AbstractEventTestCase
{
    public function testUrlVerificationEventCreation(): void
    {
        $eventData = [
            'token' => 'token_123',
            'type' => 'url_verification'];

        $event = new UrlVerificationEvent('challenge_123', $eventData);

        $this->assertSame('challenge_123', $event->getChallenge());
        $this->assertSame($eventData, $event->getData());
    }

    public function testGetChallenge(): void
    {
        $event = new UrlVerificationEvent('challenge_123', []);

        $this->assertSame('challenge_123', $event->getChallenge());
    }

    public function testGetChallengeWithEmptyChallenge(): void
    {
        $event = new UrlVerificationEvent('', []);

        $this->assertSame('', $event->getChallenge());
    }

    public function testGetData(): void
    {
        $eventData = [
            'token' => 'token_123',
            'type' => 'url_verification'];
        $event = new UrlVerificationEvent('challenge_123', $eventData);

        $this->assertSame($eventData, $event->getData());
    }

    public function testGetDataWithEmptyData(): void
    {
        $event = new UrlVerificationEvent('challenge_123', []);

        $this->assertSame([], $event->getData());
    }
}
