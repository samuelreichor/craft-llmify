<?php

namespace samuelreichor\llmify\services;

use Craft;
use samuelreichor\llmify\Constants;
use Throwable;
use yii\web\ForbiddenHttpException;

class PermissionService
{
    /**
     * @throws Throwable
     */
    public static function requireEditContentSettings(): bool
    {
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user->can(Constants::PERMISSION_EDIT_CONTENT)) {
            throw new ForbiddenHttpException();
        }

        return true;
    }

    /**
     * @throws Throwable
     */
    public static function requireEditSiteSettings(): bool
    {
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user->can(Constants::PERMISSION_EDIT_SITE)) {
            throw new ForbiddenHttpException();
        }

        return true;
    }

    /**
     * @throws Throwable
     */
    public static function canGenerate(): bool
    {
        $user = Craft::$app->getUser()->getIdentity();
        if ($user->can(Constants::PERMISSION_GENERATE)) {
            return true;
        }

        return false;
    }

    /**
     * @throws Throwable
     */
    public static function canClear(): bool
    {
        $user = Craft::$app->getUser()->getIdentity();
        if ($user->can(Constants::PERMISSION_CLEAR)) {
            return true;
        }

        return false;
    }

    /**
     * @throws Throwable
     */
    public static function canViewSidebarPanel(): bool
    {
        $user = Craft::$app->getUser()->getIdentity();
        if ($user->can(Constants::PERMISSION_VIEW_SIDEBAR_PANEL)) {
            return true;
        }

        return false;
    }
}
