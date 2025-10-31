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
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\LarkAppBotBundle\Entity\GroupInfo;

#[AdminCrud(
    routePath: '/lark-app-bot/group-info',
    routeName: 'lark_app_bot_group_info'
)]
final class GroupInfoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return GroupInfo::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('群组信息')
            ->setEntityLabelInPlural('群组信息管理')
            ->setPageTitle(Crud::PAGE_INDEX, '群组信息列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建群组信息')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑群组信息')
            ->setPageTitle(Crud::PAGE_DETAIL, '群组信息详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['chatId', 'name', 'ownerId', 'chatType'])
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
        yield TextField::new('chatId', '群组ID');
        yield TextField::new('name', '群组名称');
        yield TextareaField::new('description', '群组描述')
            ->setNumOfRows(2)
            ->hideOnIndex()
        ;
        yield TextField::new('ownerId', '群主ID')->hideOnIndex();
        yield IntegerField::new('memberCount', '成员数量');
        yield IntegerField::new('botCount', '机器人数量')->hideOnIndex();
        yield TextField::new('chatType', '群组类型')->hideOnIndex();
        yield BooleanField::new('external', '外部群组')
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
            ->add(BooleanFilter::new('external', '外部群组'))
            ->add(NumericFilter::new('memberCount', '成员数量'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }
}
