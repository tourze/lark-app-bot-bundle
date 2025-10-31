<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\LarkAppBotBundle\Entity\ApiLog;

#[AdminCrud(
    routePath: '/lark-app-bot/api-log',
    routeName: 'lark_app_bot_api_log'
)]
final class ApiLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ApiLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('API日志')
            ->setEntityLabelInPlural('API日志管理')
            ->setPageTitle(Crud::PAGE_INDEX, 'API日志列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建API日志')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑API日志')
            ->setPageTitle(Crud::PAGE_DETAIL, 'API日志详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['endpoint', 'userId'])
            ->showEntityActionsInlined()
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    /**
     * @return iterable<FieldInterface|string>
     */
    public function configureFields(string $pageName): iterable
    {
        yield from $this->getBasicFields();
        yield from $this->getConditionalFields($pageName);
        yield DateTimeField::new('createTime', '创建时间')->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('method', 'HTTP方法')->setChoices([
                'GET' => 'GET',
                'POST' => 'POST',
                'PUT' => 'PUT',
                'DELETE' => 'DELETE',
                'PATCH' => 'PATCH',
                'HEAD' => 'HEAD',
                'OPTIONS' => 'OPTIONS',
            ]))
            ->add(NumericFilter::new('statusCode', '状态码'))
            ->add(NumericFilter::new('responseTime', '响应时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }

    /**
     * @return iterable<FieldInterface|string>
     */
    private function getBasicFields(): iterable
    {
        yield IdField::new('id', 'ID')->onlyOnIndex();
        yield TextField::new('endpoint', 'API端点');
        yield $this->createMethodField();
        yield $this->createStatusCodeField();
        yield $this->createResponseTimeField();
        yield TextField::new('userId', '用户ID')->hideOnIndex();
    }

    /**
     * @return iterable<FieldInterface|string>
     */
    private function getConditionalFields(string $pageName): iterable
    {
        if (Crud::PAGE_DETAIL === $pageName) {
            yield CodeEditorField::new('requestData', '请求数据')
                ->setLanguage('javascript')
                ->setNumOfRows(10)
                ->hideOnIndex()
            ;
            yield CodeEditorField::new('responseData', '响应数据')
                ->setLanguage('javascript')
                ->setNumOfRows(15)
                ->hideOnIndex()
            ;
        }
    }

    private function createMethodField(): ChoiceField
    {
        return ChoiceField::new('method', 'HTTP方法')
            ->setChoices($this->getHttpMethods())
            ->renderAsBadges($this->getMethodBadgeColors())
        ;
    }

    private function createStatusCodeField(): IntegerField
    {
        return IntegerField::new('statusCode', '状态码')
            ->formatValue(function ($value) {
                \assert(\is_int($value));
                $class = $this->getStatusCodeClass($value);

                return \sprintf('<span class="badge badge-%s">%d</span>', $class, $value);
            })
        ;
    }

    private function createResponseTimeField(): IntegerField
    {
        return IntegerField::new('responseTime', '响应时间(ms)')
            ->formatValue(function ($value) {
                if (null === $value) {
                    return '-';
                }
                \assert(\is_int($value));
                $class = $this->getResponseTimeClass($value);

                return \sprintf('<span class="text-%s">%d ms</span>', $class, $value);
            })
            ->hideOnIndex()
        ;
    }

    /**
     * @return array<string, string>
     */
    private function getHttpMethods(): array
    {
        return [
            'GET' => 'GET',
            'POST' => 'POST',
            'PUT' => 'PUT',
            'DELETE' => 'DELETE',
            'PATCH' => 'PATCH',
            'HEAD' => 'HEAD',
            'OPTIONS' => 'OPTIONS',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getMethodBadgeColors(): array
    {
        return [
            'GET' => 'success',
            'POST' => 'primary',
            'PUT' => 'warning',
            'DELETE' => 'danger',
            'PATCH' => 'info',
            'HEAD' => 'secondary',
            'OPTIONS' => 'light',
        ];
    }

    private function getStatusCodeClass(int $value): string
    {
        return match (true) {
            $value >= 200 && $value < 300 => 'success',
            $value >= 300 && $value < 400 => 'info',
            $value >= 400 && $value < 500 => 'warning',
            $value >= 500 => 'danger',
            default => 'secondary',
        };
    }

    private function getResponseTimeClass(int $value): string
    {
        return match (true) {
            $value > 1000 => 'danger',
            $value > 500 => 'warning',
            default => 'success',
        };
    }
}
