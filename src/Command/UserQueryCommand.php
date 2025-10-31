<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Output\BatchResultFormatter;
use Tourze\LarkAppBotBundle\Command\Output\CsvOutputHelper;
use Tourze\LarkAppBotBundle\Command\Output\UserInfoFormatter;
use Tourze\LarkAppBotBundle\Exception\UnsupportedTypeException;
use Tourze\LarkAppBotBundle\Service\User\UserServiceInterface;
use Tourze\LarkAppBotBundle\Service\User\UserTools;

/**
 * 用户查询的控制台命令.
 */
#[AsCommand(name: self::NAME, description: '查询飞书用户信息', aliases: ['lark:user'], help: <<<'TXT'
    <info>基本查询：</info>
      <comment>%command.full_name% open_123456</comment>
      <comment>%command.full_name% user@example.com</comment>
      <comment>%command.full_name% +8613800138000</comment>

    <info>指定类型查询：</info>
      <comment>%command.full_name% 123456 --type=user_id</comment>
      <comment>%command.full_name% user@example.com --type=email</comment>

    <info>显示额外信息：</info>
      <comment>%command.full_name% open_123456 --department --groups</comment>

    <info>批量查询（从文件）：</info>
      <comment>cat users.txt | %command.full_name% - --batch</comment>

    <info>自定义输出格式：</info>
      <comment>%command.full_name% open_123456 --format=json</comment>
      <comment>%command.full_name% open_123456 --format=csv --fields=name --fields=email --fields=mobile</comment>

    <info>导出用户信息：</info>
      <comment>%command.full_name% open_123456 --format=json > user_info.json</comment>

    支持的字段：
    - open_id: Open ID
    - user_id: 用户ID
    - name: 姓名
    - en_name: 英文名
    - email: 邮箱
    - mobile: 手机号
    - avatar: 头像URL
    - department_ids: 部门ID列表
    - status: 用户状态
    - employee_type: 员工类型
    - join_time: 入职时间
    - city: 城市
    - country: 国家
    TXT)]
class UserQueryCommand extends Command
{
    public const NAME = 'lark:user:query';

    public function __construct(
        private readonly UserServiceInterface $userService,
        private readonly UserInfoFormatter $userInfoFormatter = new UserInfoFormatter(),
        private readonly BatchResultFormatter $batchResultFormatter = new BatchResultFormatter(),
        private readonly CsvOutputHelper $csvOutputHelper = new CsvOutputHelper(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'identifier',
                InputArgument::REQUIRED,
                '用户标识符（open_id、user_id、email 或手机号）'
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                '标识符类型：auto（自动识别）、open_id、user_id、email、mobile',
                'auto'
            )
            ->addOption(
                'batch',
                'b',
                InputOption::VALUE_NONE,
                '批量查询模式（从标准输入读取多个用户标识符）'
            )
            ->addOption(
                'department',
                'd',
                InputOption::VALUE_NONE,
                '显示用户所在部门信息'
            )
            ->addOption(
                'groups',
                'g',
                InputOption::VALUE_NONE,
                '显示用户所在群组列表'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                '输出格式：table（表格）、json、csv',
                'table'
            )
            ->addOption(
                'fields',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                '要显示的字段（可多次使用）',
                []
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->extractExecutionContext($input);

        try {
            return $context->isBatch
                ? $this->batchQuery($io, $context->type, $context->format, $context->fields)
                : $this->executeSingleUserQuery($io, $context);
        } catch (\Exception $e) {
            return $this->handleExecutionError($io, $output, $e);
        }
    }

    private function extractExecutionContext(InputInterface $input): CommandExecutionContext
    {
        $identifier = $input->getArgument('identifier');
        \assert(\is_string($identifier));

        $type = $input->getOption('type');
        \assert(\is_string($type));

        $isBatch = $input->getOption('batch');
        \assert(\is_bool($isBatch));

        $showDepartment = $input->getOption('department');
        \assert(\is_bool($showDepartment));

        $showGroups = $input->getOption('groups');
        \assert(\is_bool($showGroups));

        $format = $input->getOption('format');
        \assert(\is_string($format));

        $fields = $input->getOption('fields');
        \assert(\is_array($fields));
        /** @var array<string> $fields */
        $fields = array_filter($fields, 'is_string');

        return new CommandExecutionContext(
            identifier: $identifier,
            type: $type,
            isBatch: $isBatch,
            showDepartment: $showDepartment,
            showGroups: $showGroups,
            format: $format,
            fields: $fields
        );
    }

    private function executeSingleUserQuery(SymfonyStyle $io, CommandExecutionContext $context): int
    {
        $userInfo = $this->queryUser($context->identifier, $context->type);

        if (null === $userInfo) {
            $io->error(\sprintf('未找到用户: %s', $context->identifier));

            return Command::FAILURE;
        }

        $userInfo = $this->enrichUserInfo($userInfo, $context);
        $this->outputResult($io, $userInfo, $context->format, $context->fields);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $userInfo
     *
     * @return array<string, mixed>
     */
    private function enrichUserInfo(array $userInfo, CommandExecutionContext $context): array
    {
        if ($context->showDepartment) {
            $userInfo['departments'] = $this->getDepartmentsForUser($userInfo);
        }

        if ($context->showGroups) {
            $userInfo['groups'] = [];
        }

        return $userInfo;
    }

    /**
     * @param array<string, mixed> $userInfo
     *
     * @return array<mixed>
     */
    private function getDepartmentsForUser(array $userInfo): array
    {
        $openId = $userInfo['open_id'] ?? null;

        if (null !== $openId && \is_string($openId)) {
            return $this->getUserDepartments($openId);
        }

        return [];
    }

    private function handleExecutionError(SymfonyStyle $io, OutputInterface $output, \Exception $e): int
    {
        $io->error(\sprintf('查询失败: %s', $e->getMessage()));

        if ($output->isVeryVerbose()) {
            $io->section('错误详情');
            $io->text($e->getTraceAsString());
        }

        return Command::FAILURE;
    }

    /**
     * 查询单个用户.
     */
    /**
     * @return array<string, mixed>|null
     */
    private function queryUser(string $identifier, string $type): ?array
    {
        // 自动识别类型
        if ('auto' === $type) {
            $type = $this->detectIdentifierType($identifier);
        }

        return match ($type) {
            'open_id' => $this->userService->getUserInfo($identifier, 'open_id'),
            'user_id' => $this->userService->getUserInfo($identifier, 'user_id'),
            'email' => UserTools::getUserByEmail($this->userService, $identifier),
            'mobile' => UserTools::getUserByMobile($this->userService, $identifier),
            default => throw UnsupportedTypeException::create($type, ['open_id', 'user_id', 'email', 'mobile'], '标识符'),
        };
    }

    /**
     * 自动检测标识符类型.
     */
    private function detectIdentifierType(string $identifier): string
    {
        // Email
        if (false !== filter_var($identifier, \FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        // Mobile (简单判断)
        if (1 === preg_match('/^\+?\d{10,15}$/', $identifier)) {
            return 'mobile';
        }

        // Open ID (通常以 open_ 开头)
        if (str_starts_with($identifier, 'open_')) {
            return 'open_id';
        }

        // 默认作为 user_id
        return 'user_id';
    }

    /**
     * 批量查询.
     *
     * @param array<string> $fields
     */
    private function batchQuery(SymfonyStyle $io, string $type, string $format, array $fields): int
    {
        $identifiers = $this->readIdentifiersFromStdin();

        if ([] === $identifiers) {
            $io->warning('没有读取到任何用户标识符');

            return Command::SUCCESS;
        }

        [$results, $errors] = $this->processBatchQuery($io, $identifiers, $type);
        \assert(\is_array($results));
        \assert(\is_array($errors));
        $this->displayBatchSummary($io, $results, $format, $fields, $errors);

        return Command::SUCCESS;
    }

    /**
     * 从标准输入读取标识符.
     */
    /**
     * @return array<string>
     */
    private function readIdentifiersFromStdin(): array
    {
        $identifiers = [];
        while (false !== ($line = fgets(\STDIN))) {
            $line = trim($line);
            if ('' !== $line) {
                $identifiers[] = $line;
            }
        }

        return $identifiers;
    }

    /**
     * 处理批量查询.
     *
     * @param array<string> $identifiers
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string>}
     */
    private function processBatchQuery(SymfonyStyle $io, array $identifiers, string $type): array
    {
        $io->progressStart(\count($identifiers));
        /** @var array<int, array<string, mixed>> */
        $results = [];
        /** @var array<string> */
        $errors = [];

        foreach ($identifiers as $identifier) {
            try {
                $userInfo = $this->queryUser($identifier, $type);
                if (null !== $userInfo) {
                    $results[] = $userInfo;
                } else {
                    $errors[] = $identifier;
                }
            } catch (\Exception $e) {
                $errors[] = \sprintf('%s (%s)', $identifier, $e->getMessage());
            }
            $io->progressAdvance();
        }

        $io->progressFinish();

        return [$results, $errors];
    }

    /**
     * 显示批量查询摘要
     *
     * @param array<int, array<string, mixed>> $results
     * @param array<string>                    $fields
     * @param array<string>                    $errors
     */
    private function displayBatchSummary(SymfonyStyle $io, array $results, string $format, array $fields, array $errors): void
    {
        if ([] !== $results) {
            $this->outputBatchResults($io, $results, $format, $fields);
        }

        if ([] !== $errors) {
            $io->section('查询失败的用户');
            $io->listing($errors);
        }

        $io->success(\sprintf('成功查询 %d 个用户，失败 %d 个', \count($results), \count($errors)));
    }

    /**
     * 获取用户所在部门.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getUserDepartments(string $openId): array
    {
        try {
            $departments = $this->userService->getUserDepartments($openId);

            return array_values(array_map(function ($dept): array {
                if (!\is_array($dept)) {
                    return ['id' => 'N/A', 'name' => 'N/A', 'path' => []];
                }

                return [
                    'id' => $dept['department_id'] ?? 'N/A',
                    'name' => $dept['name'] ?? 'N/A',
                    'path' => $dept['department_path'] ?? [],
                ];
            }, $departments));
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 输出单个结果.
     *
     * @param array<string, mixed> $userInfo
     * @param array<string>        $fields
     */
    private function outputResult(SymfonyStyle $io, array $userInfo, string $format, array $fields): void
    {
        match ($format) {
            'json' => $this->outputJsonResult($io, $userInfo, $fields),
            'csv' => $this->outputCsvResult($io, $userInfo, $fields),
            default => $this->outputTable($io, $userInfo),
        };
    }

    /**
     * 输出表格格式.
     *
     * @param array<string, mixed> $userInfo
     */
    private function outputTable(SymfonyStyle $io, array $userInfo): void
    {
        $this->userInfoFormatter->outputTable($io, $userInfo);
    }

    /**
     * 输出批量结果.
     *
     * @param array<int, array<string, mixed>> $results
     * @param array<string>                    $fields
     */
    private function outputBatchResults(SymfonyStyle $io, array $results, string $format, array $fields): void
    {
        $this->batchResultFormatter->outputBatchResults($io, $results, $format, $fields);
    }

    /**
     * 过滤字段.
     *
     * @param array<string, mixed> $data
     * @param array<string>        $fields
     *
     * @return array<string, mixed>
     */
    private function filterFields(array $data, array $fields): array
    {
        $filtered = [];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $filtered[$field] = $data[$field];
            }
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $userInfo
     * @param array<string>        $fields
     */
    private function outputJsonResult(SymfonyStyle $io, array $userInfo, array $fields): void
    {
        $data = [] === $fields ? $userInfo : $this->filterFields($userInfo, $fields);
        $jsonOutput = json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
        $io->writeln(false !== $jsonOutput ? $jsonOutput : '{}');
    }

    /**
     * @param array<string, mixed> $userInfo
     * @param array<string>        $fields
     */
    private function outputCsvResult(SymfonyStyle $io, array $userInfo, array $fields): void
    {
        $data = [] === $fields ? $userInfo : $this->filterFields($userInfo, $fields);
        $this->csvOutputHelper->outputCsv($io, [$data], array_keys($data));
    }
}
