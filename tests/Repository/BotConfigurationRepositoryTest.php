<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Entity\BotConfiguration;
use Tourze\LarkAppBotBundle\Repository\BotConfigurationRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(BotConfigurationRepository::class)]
#[RunTestsInSeparateProcesses]
final class BotConfigurationRepositoryTest extends AbstractRepositoryTestCase
{
    public function testSaveAndFindBotConfigurationShouldWorkCorrectly(): void
    {
        $config = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config);

        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $em->persist($config);
        $em->flush();

        $foundConfig = $repository->findByAppIdAndKey($config->getAppId(), $config->getConfigKey());
        self::assertNotNull($foundConfig);
        self::assertSame($config->getConfigValue(), $foundConfig->getConfigValue());
    }

    public function testFindByAppIdAndKeyShouldReturnCorrectConfig(): void
    {
        $appId = 'app_test_' . uniqid();
        $configKey = 'test_key_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $config = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config);
        $config->setAppId($appId);
        $config->setConfigKey($configKey);

        $em->persist($config);
        $em->flush();

        $foundConfig = $repository->findByAppIdAndKey($appId, $configKey);
        self::assertNotNull($foundConfig);
        self::assertSame($appId, $foundConfig->getAppId());
        self::assertSame($configKey, $foundConfig->getConfigKey());
    }

    public function testFindByAppIdShouldReturnAllConfigsForApp(): void
    {
        $appId = 'app_test_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create two configs for same app
        $config1 = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config1);
        $config1->setAppId($appId);
        $config1->setConfigKey('key1');

        $config2 = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config2);
        $config2->setAppId($appId);
        $config2->setConfigKey('key2');

        $em->persist($config1);
        $em->persist($config2);
        $em->flush();

        $configs = $repository->findByAppId($appId);
        $this->assertIsArray($configs);
        self::assertCount(2, $configs);
        foreach ($configs as $config) {
            self::assertSame($appId, $config->getAppId());
        }
    }

    public function testFindActiveByAppIdShouldReturnOnlyActiveConfigs(): void
    {
        $appId = 'app_test_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create active config
        $activeConfig = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $activeConfig);
        $activeConfig->setAppId($appId);
        $activeConfig->setConfigKey('active_key');
        $activeConfig->setIsActive(true);

        // Create inactive config
        $inactiveConfig = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $inactiveConfig);
        $inactiveConfig->setAppId($appId);
        $inactiveConfig->setConfigKey('inactive_key');
        $inactiveConfig->setIsActive(false);

        $em->persist($activeConfig);
        $em->persist($inactiveConfig);
        $em->flush();

        $configs = $repository->findActiveByAppId($appId);
        $this->assertIsArray($configs);
        self::assertCount(1, $configs);
        self::assertTrue($configs[0]->getIsActive());
    }

    public function testFindActiveConfigurationsShouldReturnOnlyActiveConfigs(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create active config
        $activeConfig = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $activeConfig);
        $activeConfig->setIsActive(true);

        // Create inactive config
        $inactiveConfig = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $inactiveConfig);
        $inactiveConfig->setIsActive(false);

        $em->persist($activeConfig);
        $em->persist($inactiveConfig);
        $em->flush();

        $configs = $repository->findActiveConfigurations();
        self::assertNotEmpty($configs);
        foreach ($configs as $config) {
            self::assertTrue($config->getIsActive());
        }
    }

    public function testFindInactiveConfigurationsShouldReturnOnlyInactiveConfigs(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create active config
        $activeConfig = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $activeConfig);
        $activeConfig->setIsActive(true);

        // Create inactive config
        $inactiveConfig = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $inactiveConfig);
        $inactiveConfig->setIsActive(false);

        $em->persist($activeConfig);
        $em->persist($inactiveConfig);
        $em->flush();

        $configs = $repository->findInactiveConfigurations();
        self::assertNotEmpty($configs);
        foreach ($configs as $config) {
            self::assertFalse($config->getIsActive());
        }
    }

    public function testSearchByConfigKeyShouldReturnMatchingConfigs(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $keyword = 'search_test_' . uniqid();
        $config = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config);
        $config->setConfigKey('prefix_' . $keyword . '_suffix');

        $em->persist($config);
        $em->flush();

        $results = $repository->searchByConfigKey($keyword);
        self::assertNotEmpty($results);
        foreach ($results as $foundConfig) {
            self::assertStringContainsString($keyword, $foundConfig->getConfigKey());
        }
    }

    public function testSearchByNameShouldReturnMatchingConfigs(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $keyword = 'search_name_' . uniqid();
        $config = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config);
        $config->setName('Test ' . $keyword . ' Config');

        $em->persist($config);
        $em->flush();

        $results = $repository->searchByName($keyword);
        self::assertNotEmpty($results);
        foreach ($results as $foundConfig) {
            self::assertStringContainsString($keyword, $foundConfig->getName());
        }
    }

    public function testGetDistinctAppIdsShouldReturnUniqueAppIds(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $appId1 = 'app1_' . uniqid();
        $appId2 = 'app2_' . uniqid();

        // Create configs for different apps
        $config1 = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config1);
        $config1->setAppId($appId1);

        $config2 = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config2);
        $config2->setAppId($appId1); // Same app

        $config3 = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config3);
        $config3->setAppId($appId2); // Different app

        $em->persist($config1);
        $em->persist($config2);
        $em->persist($config3);
        $em->flush();

        $appIds = $repository->getDistinctAppIds();
        self::assertContains($appId1, $appIds);
        self::assertContains($appId2, $appIds);
        // Should not have duplicates
        self::assertSame(array_unique($appIds), $appIds);
    }

    public function testCountConfigurationsByAppIdShouldReturnCorrectCounts(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $appId = 'app_test_' . uniqid();

        // Create multiple configs for same app
        for ($i = 0; $i < 3; ++$i) {
            $config = $this->createNewEntity();
            self::assertInstanceOf(BotConfiguration::class, $config);
            $config->setAppId($appId);
            $config->setConfigKey('key_' . $i);
            $em->persist($config);
        }
        $em->flush();

        $counts = $repository->countConfigurationsByAppId();
        $this->assertIsArray($counts);
        self::assertArrayHasKey($appId, $counts);
        self::assertSame(3, $counts[$appId]);
    }

    public function testGetActivationStatsShouldReturnCorrectStats(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create active config
        $activeConfig = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $activeConfig);
        $activeConfig->setIsActive(true);

        // Create inactive config
        $inactiveConfig = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $inactiveConfig);
        $inactiveConfig->setIsActive(false);

        $em->persist($activeConfig);
        $em->persist($inactiveConfig);
        $em->flush();

        $stats = $repository->getActivationStats();
        $this->assertIsArray($stats);
        self::assertArrayHasKey('active', $stats);
        self::assertArrayHasKey('inactive', $stats);
        self::assertGreaterThanOrEqual(1, $stats['active']);
        self::assertGreaterThanOrEqual(1, $stats['inactive']);
    }

    public function testUniqueConstraintShouldPreventDuplicateAppIdConfigKey(): void
    {
        $appId = 'app_test_' . uniqid();
        $configKey = 'duplicate_key';
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $config1 = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config1);
        $config1->setAppId($appId);
        $config1->setConfigKey($configKey);

        $config2 = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config2);
        $config2->setAppId($appId);
        $config2->setConfigKey($configKey);

        $em->persist($config1);
        $em->flush();

        // Second config with same app_id and config_key should cause constraint violation
        $this->expectException(\Exception::class);
        $em->persist($config2);
        $em->flush();
    }

    public function testSaveMethodShouldPersistEntity(): void
    {
        $repository = $this->getRepository();
        $config = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config);

        // Test save without flush
        $repository->save($config, false);

        // Entity should be persisted but not flushed yet
        $em = self::getEntityManager();
        self::assertTrue($em->contains($config));

        // Now flush and verify it's saved
        $em->flush();
        $foundConfig = $repository->findByAppIdAndKey($config->getAppId(), $config->getConfigKey());
        self::assertNotNull($foundConfig);
        self::assertSame($config->getId(), $foundConfig->getId());
    }

    public function testSaveMethodWithFlushShouldPersistAndFlushEntity(): void
    {
        $repository = $this->getRepository();
        $config = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config);

        // Test save with flush (default behavior)
        $repository->save($config);

        // Entity should be immediately available
        $foundConfig = $repository->findByAppIdAndKey($config->getAppId(), $config->getConfigKey());
        self::assertNotNull($foundConfig);
        self::assertSame($config->getId(), $foundConfig->getId());
    }

    public function testRemoveMethodShouldDeleteEntity(): void
    {
        $repository = $this->getRepository();
        $config = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config);

        // First save the entity
        $repository->save($config);
        $configId = $config->getId();

        // Verify it exists
        $foundConfig = $repository->find($configId);
        self::assertNotNull($foundConfig);

        // Now remove it
        $repository->remove($config);

        // Verify it's deleted
        $deletedConfig = $repository->find($configId);
        self::assertNull($deletedConfig);
    }

    public function testRemoveMethodWithoutFlushShouldNotDeleteImmediately(): void
    {
        $repository = $this->getRepository();
        $config = $this->createNewEntity();
        self::assertInstanceOf(BotConfiguration::class, $config);

        // First save the entity
        $repository->save($config);
        $configId = $config->getId();

        // Remove without flush
        $repository->remove($config, false);

        // Entity should still be findable before flush
        $foundConfig = $repository->find($configId);
        self::assertNotNull($foundConfig);

        // After flush, it should be deleted
        self::getEntityManager()->flush();
        $deletedConfig = $repository->find($configId);
        self::assertNull($deletedConfig);
    }

    protected function onSetUp(): void
    {
        // No setup required - using self::getService() directly in tests
    }

    protected function createNewEntity(): object
    {
        $config = new BotConfiguration();
        $config->setAppId('app_' . uniqid());
        $config->setName('Test Config ' . uniqid());
        $config->setConfigKey('test_key_' . uniqid());
        $config->setConfigValue('test_value_' . uniqid());
        $config->setDescription('Test configuration for unit tests');
        $config->setIsActive(true);

        return $config;
    }

    protected function getRepository(): BotConfigurationRepository
    {
        return self::getService(BotConfigurationRepository::class);
    }
}
