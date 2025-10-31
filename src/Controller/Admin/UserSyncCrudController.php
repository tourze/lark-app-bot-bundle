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
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Tourze\LarkAppBotBundle\Entity\UserSync;

#[AdminCrud(
    routePath: '/lark-app-bot/user-sync',
    routeName: 'lark_app_bot_user_sync'
)]
final class UserSyncCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserSync::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('用户同步')
            ->setEntityLabelInPlural('用户同步管理')
            ->setPageTitle(Crud::PAGE_INDEX, '用户同步列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建用户同步')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑用户同步')
            ->setPageTitle(Crud::PAGE_DETAIL, '用户同步详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['userId', 'openId', 'unionId', 'name', 'email'])
            ->showEntityActionsInlined()
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    /**
     * @return iterable<FieldInterface|string>
     */
    public function configureFields(string $pageName): iterable
    {
        $syncStatusField = ChoiceField::new('syncStatus', '同步状态')
            ->setChoices([
                '待同步' => 'pending',
                '成功' => 'success',
                '失败' => 'failed',
            ])
            ->renderAsBadges([
                'pending' => 'warning',
                'success' => 'success',
                'failed' => 'danger',
            ])
        ;

        yield IdField::new('id', 'ID')->onlyOnIndex();
        yield TextField::new('userId', '飞书用户ID');
        yield TextField::new('openId', 'Open ID')->hideOnIndex();
        yield TextField::new('unionId', 'Union ID')->hideOnIndex();
        yield TextField::new('name', '用户名');
        yield EmailField::new('email', '邮箱')->hideOnIndex();
        yield TextField::new('mobile', '手机号')->hideOnIndex();

        if (Crud::PAGE_DETAIL === $pageName || Crud::PAGE_EDIT === $pageName || Crud::PAGE_NEW === $pageName) {
            yield CodeEditorField::new('departmentIds', '部门ID')
                ->setLanguage('javascript')
                ->setNumOfRows(5)
                ->hideOnIndex()
            ;
        }

        yield $syncStatusField;
        yield DateTimeField::new('syncAt', '同步时间')->hideOnIndex();

        if (Crud::PAGE_DETAIL === $pageName || Crud::PAGE_EDIT === $pageName || Crud::PAGE_NEW === $pageName) {
            yield TextareaField::new('errorMessage', '错误信息')
                ->setNumOfRows(3)
                ->hideOnIndex()
            ;
        }

        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
        ;
        yield DateTimeField::new('updateTime', '更新时间')
            ->onlyOnDetail()
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('syncStatus', '同步状态')->setChoices([
                '待同步' => 'pending',
                '成功' => 'success',
                '失败' => 'failed',
            ]))
            ->add(DateTimeFilter::new('syncAt', '同步时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }
}
