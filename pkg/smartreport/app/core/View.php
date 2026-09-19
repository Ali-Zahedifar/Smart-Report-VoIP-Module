<?php

namespace SmartReport\Core;

use Exception;

final class View
{
    public static function render($template, array $data = [], $withLayout = true)
    {
        $content = self::partial($template, $data);

        if (!$withLayout) {
            echo $content;
            return;
        }

        $data = self::layoutDefaults($data);
        $data['content'] = $content;
        echo self::partial('layout', $data);
    }

    public static function partial($template, array $data = [])
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include self::resolve($template);
        return (string) ob_get_clean();
    }

    private static function resolve($template)
    {
        if (starts($template, ':')) {
            return SMR_APP . '/' . substr($template, 1) . '.php';
        }
        return SMR_TEMPLATES . '/' . $template . '.php';
    }

    private static function layoutDefaults(array $data)
    {
        if (!isset($data['brand'])) {
            try {
                $data['brand'] = App::name();
            } catch (Exception $e) {
                $data['brand'] = Config::get('app.name', 'Smart-Report');
            }
        }
        if (!isset($data['menuItems'])) {
            $data['menuItems'] = FeatureRegistry::menu();
        }
        if (!isset($data['user'])) {
            $data['user'] = Auth::user();
        }
        if (!isset($data['currentLanguage'])) {
            $data['currentLanguage'] = Lang::current();
        }
        if (!isset($data['languages'])) {
            $data['languages'] = Lang::languages();
        }
        if (!isset($data['isRtl'])) {
            $data['isRtl'] = Lang::isRtl();
        }
        if (!isset($data['appVersion'])) {
            $data['appVersion'] = Config::get('app.version', SMR_VERSION);
        }
        if (!isset($data['flash'])) {
            $data['flash'] = flash_messages();
        }
        if (!isset($data['currentRoute'])) {
            $data['currentRoute'] = current_path();
        }
        return $data;
    }
}