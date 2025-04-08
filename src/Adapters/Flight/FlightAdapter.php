<?php
namespace YModules\Adapters\Flight;

use YModules\Core\PlatformAdapterInterface;
use YModules\Core\ModuleLoader;

/**
 * Flight Adapter
 * 
 * Интегрирует YModules с Flight PHP
 */
class FlightAdapter implements PlatformAdapterInterface {
    /** @var ModuleLoader Экземпляр загрузчика модулей */
    private $module_loader;
    
    /** @var string Базовый путь */
    private $base_path;
    
    /** @var string URL для ассетов */
    private $assets_url;
    
    /** @var string Название опции для хранения активных модулей */
    const ACTIVE_MODULES_OPTION = 'ymodules_active_modules';
    
    /**
     * Конструктор
     * 
     * @param string $base_path Базовый путь
     * @param string $modules_path Путь к модулям
     * @param string $assets_path Путь к ассетам
     * @param string $assets_url URL для ассетов
     */
    public function __construct($base_path, $modules_path = null, $assets_path = null, $assets_url = null) {
        $this->base_path = rtrim($base_path, '/') . '/';
        $this->assets_url = $assets_url ?: site_url() . '/wp-content/plugins/mland/assets/';
        
        // Определяем пути для модулей и ассетов
        $modules_path = $modules_path ?: (defined('YMODULES_MODULES_DIR') ? YMODULES_MODULES_DIR : $this->base_path . 'modules/');
        $assets_path = $assets_path ?: (defined('YMODULES_ASSETS_DIR') ? YMODULES_ASSETS_DIR : $this->base_path . 'assets/');
        
        // Инициализируем ModuleLoader
        $this->module_loader = new ModuleLoader(
            $this->base_path,
            $modules_path,
            $assets_path,
            $this->getActiveModules()
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function initialize() {
        // Загружаем активные модули
        $this->loadModules();
        
        return true;
    }
    
    /**
     * Загружает все активные модули
     */
    private function loadModules() {
        $result = $this->module_loader->loadActiveModules();
        
        // Логируем ошибки, если есть
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $slug => $error) {
                $this->log("Error loading module {$slug}: {$error}", 'error');
            }
        }
        
        // Обрабатываем успешно загруженные модули
        if (!empty($result['success'])) {
            foreach ($result['success'] as $slug => $data) {
                $this->log("Successfully loaded module {$slug}", 'info');
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function getActiveModules() {
        // Используем WordPress для хранения активных модулей
        return get_option(self::ACTIVE_MODULES_OPTION, []);
    }
    
    /**
     * {@inheritdoc}
     */
    public function saveActiveModules(array $modules) {
        return update_option(self::ACTIVE_MODULES_OPTION, $modules);
    }
    
    /**
     * {@inheritdoc}
     */
    public function registerAdminInterface() {
        // В Flight не используется
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function renderAdminInterface($context = []) {
        // Получаем список модулей
        $modules = $this->module_loader->getInstalledModules();
        
        // Подготавливаем данные для шаблона
        $ymodules_data = [
            'modules' => array_values($modules),
            'count' => count($modules)
        ];
        
        // Объединяем с переданным контекстом
        if (is_array($context)) {
            $ymodules_data = array_merge($ymodules_data, $context);
        }
        
        // Ищем шаблон в структуре src/templates
        $template_path = YMODULES_SRC_DIR . 'templates/admin-page.php';
        if (file_exists($template_path)) {
            include $template_path;
            return;
        }
        
        // Запасной вариант - в директории templates
        $template_path = YMODULES_PLUGIN_DIR . 'templates/admin-page.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="wrap"><p class="notice notice-error">';
            echo __('Error: Admin template not found.', 'ymodules');
            echo '</p></div>';
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function activateModule($slug) {
        // Проверяем существование модуля
        if (!$this->module_loader->moduleExists($slug)) {
            return new \WP_Error('module_not_found', __('Module not found', 'ymodules'));
        }
        
        // Активируем модуль
        if (!$this->module_loader->activateModule($slug)) {
            return new \WP_Error('activation_failed', __('Failed to activate module', 'ymodules'));
        }
        
        // Сохраняем список активных модулей
        $this->saveActiveModules($this->module_loader->getActiveModules());
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function deactivateModule($slug) {
        // Проверяем существование модуля
        if (!$this->module_loader->moduleExists($slug)) {
            return new \WP_Error('module_not_found', __('Module not found', 'ymodules'));
        }
        
        // Деактивируем модуль
        if (!$this->module_loader->deactivateModule($slug)) {
            return new \WP_Error('deactivation_failed', __('Failed to deactivate module', 'ymodules'));
        }
        
        // Сохраняем список активных модулей
        $this->saveActiveModules($this->module_loader->getActiveModules());
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function installModule($file) {
        // Перемещаем загруженный файл во временное место
        $tmp_dir = $this->getUploadDir();
        $tmp_file = $tmp_dir . uniqid('ymodules_') . '.zip';
        
        if (!move_uploaded_file($file['tmp_name'], $tmp_file)) {
            return new \WP_Error('upload_failed', __('Failed to move uploaded file', 'ymodules'));
        }
        
        // Устанавливаем модуль из ZIP-файла
        $module_info = $this->module_loader->installModuleFromZip($tmp_file);
        
        // Удаляем временный файл
        @unlink($tmp_file);
        
        if (!$module_info) {
            return new \WP_Error('install_failed', __('Failed to install module', 'ymodules'));
        }
        
        return $module_info;
    }
    
    /**
     * {@inheritdoc}
     */
    public function deleteModule($slug) {
        // Проверяем существование модуля
        if (!$this->module_loader->moduleExists($slug)) {
            return new \WP_Error('module_not_found', __('Module not found', 'ymodules'));
        }
        
        // Удаляем модуль
        if (!$this->module_loader->deleteModule($slug)) {
            return new \WP_Error('delete_failed', __('Failed to delete module', 'ymodules'));
        }
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getBasePath() {
        return $this->base_path;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getAssetUrl($relative_path) {
        return $this->assets_url . $relative_path;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getUploadDir() {
        $upload_dir = wp_upload_dir();
        $ymodules_dir = $upload_dir['basedir'] . '/ymodules/';
        
        if (!is_dir($ymodules_dir)) {
            wp_mkdir_p($ymodules_dir);
        }
        
        return $ymodules_dir;
    }
    
    /**
     * {@inheritdoc}
     */
    public function log($message, $level = 'info') {
        error_log("YModules Flight {$level}: {$message}");
    }
    
    /**
     * {@inheritdoc}
     */
    public function getModuleLoader() {
        return $this->module_loader;
    }
} 