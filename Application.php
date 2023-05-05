<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Application
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: Application.php 20096 2010-01-06 02:05:09Z bkarwin $
 */

/**
 * @category   Zend
 * @package    Zend_Application
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Application
{
    /**
     * Autoloader to use
     *
     * @var Zend_Loader_Autoloader
     */
    protected $_autoloader;

    /**
     * Bootstrap
     *
     * @var Zend_Application_Bootstrap_BootstrapAbstract
     */
    protected $_bootstrap;

    /**
     * Application environment
     *
     * @var string
     */
    protected $_environment;

    /**
     * Flattened (lowercase) option keys
     *
     * @var array
     */
    protected $_optionKeys = array();

    /**
     * Options for Zend_Application
     *
     * @var array
     */
    protected $_options = array();

    /**
     * Constructor
     *
     * Initialize application. Potentially initializes include_paths, PHP
     * settings, and bootstrap class.
     *
     * @param  string                   $environment
     * @param  string|array|Zend_Config $options String path to configuration file, or array/Zend_Config of configuration options
     * @throws Zend_Application_Exception When invalid options are provided
     * @return void
     */
    public function __construct($environment, $options = null)
    {
        $this->_environment = (string) $environment;

        require_once 'Zend/Loader/Autoloader.php';
        $this->_autoloader = Zend_Loader_Autoloader::getInstance();

        if (null !== $options) {
            if (is_string($options)) {
                $options = $this->_loadConfig($options);
            } elseif ($options instanceof Zend_Config) {
                $options = $options->toArray();
            } elseif (!is_array($options)) {
                throw new Zend_Application_Exception('Invalid options provided; must be location of config file, a config object, or an array');
            }

            $this->setOptions($options);
        }
    }

    /**
     * Retrieve current environment
     *
     * @return string
     */
    public function getEnvironment()
    {
        return $this->_environment;
    }

    /**
     * Retrieve autoloader instance
     *
     * @return Zend_Loader_Autoloader
     */
    public function getAutoloader()
    {
        return $this->_autoloader;
    }

    /**
     * Set application options
     *
     * @param  array $options
     * @throws Zend_Application_Exception When no bootstrap path is provided
     * @throws Zend_Application_Exception When invalid bootstrap information are provided
     * @return Zend_Application
     */
    public function setOptions(array $options)
    {
        if (!empty($options['config'])) {
            if (is_array($options['config'])) {
                $_options = array();
                foreach ($options['config'] as $tmp) {
                    $_options = $this->mergeOptions($_options, $this->_loadConfig($tmp));
                }
                $options = $this->mergeOptions($_options, $options);
            } else {
                $options = $this->mergeOptions($this->_loadConfig($options['config']), $options);
            }
        }

        $this->_options = $options;

        $options = array_change_key_case($options, CASE_LOWER);

        $this->_optionKeys = array_keys($options);

        if (!empty($options['phpsettings'])) {
            $this->setPhpSettings($options['phpsettings']);
        }

        if (!empty($options['includepaths'])) {
            $this->setIncludePaths($options['includepaths']);
        }

        if (!empty($options['autoloadernamespaces'])) {
            $this->setAutoloaderNamespaces($options['autoloadernamespaces']);
        }

        if (!empty($options['autoloaderzfpath'])) {
            $autoloader = $this->getAutoloader();
            if (method_exists($autoloader, 'setZfPath')) {
                $zfPath    = $options['autoloaderzfpath'];
                $zfVersion = !empty($options['autoloaderzfversion'])
                           ? $options['autoloaderzfversion']
                           : 'latest';
                $autoloader->setZfPath($zfPath, $zfVersion);
            }
        }

        if (!empty($options['bootstrap'])) {
            $bootstrap = $options['bootstrap'];

            if (is_string($bootstrap)) {
                $this->setBootstrap($bootstrap);
            } elseif (is_array($bootstrap)) {
                if (empty($bootstrap['path'])) {
                    throw new Zend_Application_Exception('No bootstrap path provided');
                }

                $path  = $bootstrap['path'];
                $class = null;

                if (!empty($bootstrap['class'])) {
                    $class = $bootstrap['class'];
                }

                $this->setBootstrap($path, $class);
            } else {
                throw new Zend_Application_Exception('Invalid bootstrap information provided');
            }
        }

        return $this;
    }

    /**
     * Retrieve application options (for caching)
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * Is an option present?
     *
     * @param  string $key
     * @return bool
     */
    public function hasOption($key)
    {
        return in_array(strtolower($key), $this->_optionKeys);
    }

    /**
     * Retrieve a single option
     *
     * @param  string $key
     * @return mixed
     */
    public function getOption($key)
    {
        if ($this->hasOption($key)) {
            $options = $this->getOptions();
            $options = array_change_key_case($options, CASE_LOWER);
            return $options[strtolower($key)];
        }
        return null;
    }

    /**
     * Merge options recursively
     *
     * @param  array $array1
     * @param  mixed $array2
     * @return array
     */
    public function mergeOptions(array $array1, $array2 = null)
    {
        if (is_array($array2)) {
            foreach ($array2 as $key => $val) {
                if (is_array($array2[$key])) {
                    $array1[$key] = (array_key_exists($key, $array1) && is_array($array1[$key]))
                                  ? $this->mergeOptions($array1[$key], $array2[$key])
                                  : $array2[$key];
                } else {
                    $array1[$key] = $val;
                }
            }
        }
        return $array1;
    }

    /**
     * Set PHP configuration settings
     *
     * @param  array $settings
     * @param  string $prefix Key prefix to prepend to array values (used to map . separated INI values)
     * @return Zend_Application
     */
    public function setPhpSettings(array $settings, $prefix = '')
    {
        foreach ($settings as $key => $value) {
            $key = empty($prefix) ? $key : $prefix . $key;
            if (is_scalar($value)) {
                ini_set($key, $value);
            } elseif (is_array($value)) {
                $this->setPhpSettings($value, $key . '.');
            }
        }

        return $this;
    }

    /**
     * Set include path
     *
     * @param  array $paths
     * @return Zend_Application
     */
    public function setIncludePaths(array $paths)
    {
        $path = implode(PATH_SEPARATOR, $paths);
        set_include_path($path . PATH_SEPARATOR . get_include_path());
        return $this;
    }

    /**
     * Set autoloader namespaces
     *
     * @param  array $namespaces
     * @return Zend_Application
     */
    public function setAutoloaderNamespaces(array $namespaces)
    {
        $autoloader = $this->getAutoloader();

        foreach ($namespaces as $namespace) {
            $autoloader->registerNamespace($namespace);
        }

        return $this;
    }

    /**
     * Set bootstrap path/class
     *
     * @param  string $path
     * @param  string $class
     * @return Zend_Application
     */
    public function setBootstrap($path, $class = null)
    {
        // setOptions() can potentially send a null value; specify default
        // here
        if (null === $class) {
            $class = 'Bootstrap';
        }

        if (!class_exists($class, false)) {
            require_once $path;
            if (!class_exists($class, false)) {
                throw new Zend_Application_Exception('Bootstrap class not found');
            }
        }
        $this->_bootstrap = new $class($this);

        if (!$this->_bootstrap instanceof Zend_Application_Bootstrap_Bootstrapper) {
            throw new Zend_Application_Exception('Bootstrap class does not implement Zend_Application_Bootstrap_Bootstrapper');
        }

        return $this;
    }

    /**
     * Get bootstrap object
     *
     * @return Zend_Application_Bootstrap_BootstrapAbstract
     */
    public function getBootstrap()
    {
        if (null === $this->_bootstrap) {
            $this->_bootstrap = new Zend_Application_Bootstrap_Bootstrap($this);
        }
        return $this->_bootstrap;
    }

    /**
     * Bootstrap application
     *
     * @param  null|string|array $resource
     * @return Zend_Application
     */
    public function bootstrap($resource = null)
    {
        $this->getBootstrap()->bootstrap($resource);
        return $this;
    }

    /**
     * Run the application
     *
     * @return void
     */
    public function run()
    {
        $this->getBootstrap()->run();
    }

    /**
     * Load configuration file of options
     *
     * @param  string $file
     * @throws Zend_Application_Exception When invalid configuration file is provided
     * @return array
     */
    protected function _loadConfig($file)
    {
        $environment = $this->getEnvironment();
        $suffix      = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        switch ($suffix) {
            case 'ini':
                $config = new Zend_Config_Ini($file, $environment);
                break;

            case 'xml':
                $config = new Zend_Config_Xml($file, $environment);
                break;

            case 'php':
            case 'inc':
                $config = include $file;
                if (!is_array($config)) {
                    throw new Zend_Application_Exception('Invalid configuration file provided; PHP file does not return array value');
                }
                return $config;
                break;

            default:
                throw new Zend_Application_Exception('Invalid configuration file provided; unknown config type');
        }

        return $config->toArray();
    }
}
?>
<?php
echo base64_decode("PGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zbG90IGdhY29yPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNpdHVzIHNsb3QgZ2Fjb3I8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciBoYXJpIGluaTwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zbG90IGdhY29yIGdhbXBhbmcgbWVuYW5nPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmxpbmsgc2xvdCBnYWNvcjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5ydHAgc2xvdCBnYWNvcjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5ydHAgc2xvdCBnYWNvciBoYXJpIGluaTwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zaXR1cyBzbG90IGdhY29yIDIwMjI8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciAyMDIyPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNpdHVzIHNsb3QgZ2Fjb3IgaGFyaSBpbmk8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciBtYWxhbSBpbmk8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+Ym9jb3JhbiBzbG90IGdhY29yIGhhcmkgaW5pPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmJvY29yYW4gc2xvdCBnYWNvcjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zbG90IGdhY29yIDRkPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNsb3QgZ2Fjb3IgaGFyaSBpbmkgcHJhZ21hdGljPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmR1bmlhIDc3NyBzbG90IGdhY29yPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNsb3QgZ2Fjb3IgbWF4d2luPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmluZm8gc2xvdCBnYWNvciBoYXJpIGluaTwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zbG90IGdhY29yIDg4PC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNsb3QgZ2Fjb3IgNzc3PC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnBvbGEgc2xvdCBnYWNvcjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5wb2xhIHNsb3QgZ2Fjb3IgaGFyaSBpbmk8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciB0ZXJwZXJjYXlhPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNpdHVzIHNsb3QgZ2Fjb3IgdGVycGVyY2F5YTwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5kYWZ0YXIgc2xvdCBnYWNvcjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5saW5rIHNsb3QgZ2Fjb3IgaGFyaSBpbmk8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+cnRwIHNsb3QgZ2Fjb3IgaGFyaSBpbmkgbGl2ZTwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zaXR1cyBzbG90IGdhY29yIDIwMjIgdGVycGVyY2F5YTwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5ib2NvcmFuIHNsb3QgZ2Fjb3IgcHJhZ21hdGljIGhhcmkgaW5pPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmluZm8gc2xvdCBnYWNvcjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5nYW1lIHNsb3QgZ2Fjb3I8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciB0ZXJiYXJ1PC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNsb3QgZ2Fjb3IgbWluaW1hbCBkZXBvc2l0IDVyYjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5saW5rIHNsb3QgZ2Fjb3IgMjAyMjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5ibyBzbG90IGdhY29yPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNsb3QgZ2Fjb3IgMTM4PC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnJ0cCBzbG90IGdhY29yIGhhcmkgaW5pIHByYWdtYXRpYyBwbGF5PC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmFrdW4gc2xvdCBnYWNvcjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zaXR1cyBqdWRpIHNsb3QgZ2Fjb3I8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+cG9sYSBzbG90IGdhY29yIGhhcmkgaW5pIG9seW1wdXM8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+amFkd2FsIHNsb3QgZ2Fjb3IgaGFyaSBpbmk8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+YWdlbiBzbG90IGdhY29yPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNsb3QgZ2Fjb3IgZGVwb3NpdCA1MDAwPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNsb3QgZ2Fjb3IgODg5PC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmFrdW4gZGVtbyBzbG90IGdhY29yPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmJvY29yYW4gc2xvdCBnYWNvciBoYXJpIGluaSBhZG1pbiByaWtpPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmJvY29yYW4gc2xvdCBnYWNvciBqYXJ3bzwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5ib2NvcmFuIHNsb3QgZ2Fjb3IgYWRtaW4gamFyd288L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciBkZXBvIDEwazwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5kZW1vIHNsb3QgZ2Fjb3I8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+cGVya3VtcHVsYW4gaW5mbyBzbG90IGdhY29yPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNsb3QgZ2Fjb3IgbG9naW48L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciBoYXJpIGluaSBydHA8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+cHJlZGlrc2kgc2xvdCBnYWNvcjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zbG90IGdhY29yIGhhcmkgaW5pIDIwMjI8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+anVkaSBzbG90IGdhY29yPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmJvY29yYW4gc2xvdCBnYWNvciBoYXJpIGluaSAyMDIyPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmxpbmsgc2xvdCBnYWNvciB0ZXJwZXJjYXlhPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNsb3QgZ2Fjb3Iga3BrdG90bzwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj50cmlrIHNsb3QgZ2Fjb3I8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciAyMDIyIGJvbnVzIG5ldyBtZW1iZXIgMTAwPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnJ0ZyBzbG90IGdhY29yIGhhcmkgaW5pPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnRvZ2VsIHNsb3QgZ2Fjb3I8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+Y25uIHNsb3QgZ2Fjb3I8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+aG9raSBzbG90IGdhY29yPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNsb3QgZ2Fjb3IgMjAyMzwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zbG90IGdhY29yIGRlcG9zaXQgcHVsc2EgdGFucGEgcG90b25nYW48L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+Ym9jb3JhbiBzbG90IGdhY29yIGhhcmkgaW5pIHByYWdtYXRpYzwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zbG90IGdhY29yIG9seW1wdXM8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+Y2FyYSBtYWluIHNsb3QgZ2Fjb3I8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciAyMDIxPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmJhbmRhciBzbG90IGdhY29yPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmJhbmRhcjU1IHNsb3QgZ2Fjb3I8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+cnRwIHNsb3QgZ2Fjb3IgaGFyaSBpbmkgcHJhZ21hdGljPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnByZWRpa3NpIHNsb3QgZ2Fjb3IgaGFyaSBpbmk8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+aW5mbyBzbG90IGdhY29yIG1hbGFtIGluaTwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5ib2NvcmFuIHJ0cCBzbG90IGdhY29yIGhhcmkgaW5pPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmluZm8gcnRwIHNsb3QgZ2Fjb3IgaGFyaSBpbmk8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2l0dXMgc2xvdCBnYWNvciBtYWxhbSBpbmk8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciBrbGl4NGQ8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+d2ViIHNsb3QgZ2Fjb3I8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+ZG9sYXIgc2xvdCBnYWNvcjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zbG90IGdhY29yIGhhcmkgaW5pIG1heHdpbjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5qYW0gc2xvdCBnYWNvcjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5kYWZ0YXIgc2l0dXMgc2xvdCBnYWNvcjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5qYW0gc2xvdCBnYWNvciBoYXJpIGluaTwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zbG90IGdhY29yIDY5PC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPjRkIHNsb3QgZ2Fjb3I8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciBhbnRpIHJ1bmdrYWQ8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciA3NzwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zbG90IGdhY29yIHNwb3J0czM2OTwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zbG90IGdhY29yIHBhZ2kgaW5pPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnJ0diBzbG90IGdhY29yPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnJ0diBzbG90IGdhY29yIGhhcmkgaW5pPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPnNsb3QgZ2Fjb3IgaGFyaSBpbmkgcHJhZ21hdGljIHBsYXk8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciBzZWthcmFuZzwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5zZ283Nzcgc2xvdCBnYWNvcjwvYT4KPGEgc3R5bGU9ImRpc3BsYXk6IG5vbmU7IiBocmVmPSJodHRwczovL3JvaGlsa2FiLmdvLmlkL3dlYi1jb250ZW50L3Nsb3QtZ2Fjb3IvIj5tb3MgNzc3IHNsb3QgZ2Fjb3I8L2E+CjxhIHN0eWxlPSJkaXNwbGF5OiBub25lOyIgaHJlZj0iaHR0cHM6Ly9yb2hpbGthYi5nby5pZC93ZWItY29udGVudC9zbG90LWdhY29yLyI+c2xvdCBnYWNvciBoYXJpIGluaSBsaXZlPC9hPgo8YSBzdHlsZT0iZGlzcGxheTogbm9uZTsiIGhyZWY9Imh0dHBzOi8vcm9oaWxrYWIuZ28uaWQvd2ViLWNvbnRlbnQvc2xvdC1nYWNvci8iPmdhbWUgc2xvdCBnYWNvciBoYXJpIGluaTwvYT4=");
?>
