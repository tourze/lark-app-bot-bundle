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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Tourze\LarkAppBotBundle\Entity\MessageRecord;

#[AdminCrud(
    routePath: '/lark-app-bot/message-record',
    routeName: 'lark_app_bot_message_record'
)]
final class MessageRecordCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MessageRecord::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('消息记录')
            ->setEntityLabelInPlural('消息记录管理')
            ->setPageTitle(Crud::PAGE_INDEX, '消息记录列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建消息记录')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑消息记录')
            ->setPageTitle(Crud::PAGE_DETAIL, '消息记录详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['messageId', 'chatId', 'senderId', 'messageType'])
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
        $chatTypeField = ChoiceField::new('chatType', '聊天类型')
            ->setChoices([
                '私聊' => 'p2p',
                '群聊' => 'group',
            ])
            ->renderAsBadges([
                'p2p' => 'info',
                'group' => 'success',
            ])
        ;

        $senderTypeField = ChoiceField::new('senderType', '发送者类型')
            ->setChoices([
                '用户' => 'user',
                '机器人' => 'bot',
            ])
            ->renderAsBadges([
                'user' => 'primary',
                'bot' => 'warning',
            ])
        ;

        $messageTypeField = ChoiceField::new('messageType', '消息类型')
            ->setChoices([
                '文本' => 'text',
                '图片' => 'image',
                '文件' => 'file',
                '卡片' => 'card',
                '视频' => 'video',
                '音频' => 'audio',
                '表情' => 'sticker',
                '富文本' => 'post',
                '分享聊天' => 'share_chat',
                '分享用户' => 'share_user',
            ])
            ->renderAsBadges([
                'text' => 'primary',
                'image' => 'info',
                'file' => 'secondary',
                'card' => 'success',
                'video' => 'warning',
                'audio' => 'info',
                'sticker' => 'light',
                'post' => 'dark',
                'share_chat' => 'success',
                'share_user' => 'info',
            ])
        ;

        yield IdField::new('id', 'ID')->onlyOnIndex();
        yield TextField::new('messageId', '消息ID');
        yield TextField::new('chatId', '聊天ID');
        yield $chatTypeField;
        yield TextField::new('senderId', '发送者ID');
        yield $senderTypeField;
        yield $messageTypeField;

        if (Crud::PAGE_DETAIL === $pageName) {
            yield CodeEditorField::new('content', '消息内容')
                ->setLanguage('javascript')
                ->setNumOfRows(15)
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
            ->add(ChoiceFilter::new('chatType', '聊天类型')->setChoices([
                '私聊' => 'p2p',
                '群聊' => 'group',
            ]))
            ->add(ChoiceFilter::new('senderType', '发送者类型')->setChoices([
                '用户' => 'user',
                '机器人' => 'bot',
            ]))
            ->add(ChoiceFilter::new('messageType', '消息类型')->setChoices([
                '文本' => 'text',
                '图片' => 'image',
                '文件' => 'file',
                '卡片' => 'card',
                '视频' => 'video',
                '音频' => 'audio',
                '表情' => 'sticker',
                '富文本' => 'post',
                '分享聊天' => 'share_chat',
                '分享用户' => 'share_user',
            ]))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }
}
