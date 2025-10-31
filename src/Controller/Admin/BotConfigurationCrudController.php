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
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Tourze\LarkAppBotBundle\Entity\BotConfiguration;

#[AdminCrud(
    routePath: '/lark-app-bot/bot-configuration',
    routeName: 'lark_app_bot_bot_configuration'
)]
final class BotConfigurationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BotConfiguration::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('机器人配置')
            ->setEntityLabelInPlural('机器人配置管理')
            ->setPageTitle(Crud::PAGE_INDEX, '机器人配置列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建机器人配置')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑机器人配置')
            ->setPageTitle(Crud::PAGE_DETAIL, '机器人配置详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['appId', 'name', 'configKey'])
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
        yield IdField::new('id', 'ID')->onlyOnIndex();
        yield TextField::new('appId', '应用ID');
        yield TextField::new('name', '配置名称');
        yield TextField::new('configKey', '配置键');

        if (Crud::PAGE_DETAIL === $pageName || Crud::PAGE_EDIT === $pageName || Crud::PAGE_NEW === $pageName) {
            yield TextareaField::new('configValue', '配置值')
                ->setNumOfRows(3)
                ->hideOnIndex()
            ;
        }

        yield TextareaField::new('description', '配置描述')
            ->setNumOfRows(2)
            ->hideOnIndex()
        ;

        yield BooleanField::new('isActive', '是否激活')
            ->renderAsSwitch(false)
        ;

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
            ->add(BooleanFilter::new('isActive', '是否激活'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }
}
