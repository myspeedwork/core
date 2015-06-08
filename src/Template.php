<?php

/**
 * This file is part of the Speedwork package.
 *
 * (c) 2s Technologies <info@2stech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Core;

use Util\Utility;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Template extends Controller
{
    public $_footer             = [];
    public $_header             = [];

    public $breadcrumbs        = [];
    public $is_ajax_request    = false;
    public $_device            = 'computer';
    public $url                = _URL;

    /**
     * File extension. Defaults to template ".tpl".
     *
     * @var string
     */
    public $ext = '.tpl';

    /**
     * Document title.
     *
     * @var string
     * @access  private
     */
    private $_title = '';

    /**
     * Document description.
     *
     * @var string
     * @access  private
     */
    private $_description = '';

    /**
     * Document description.
     *
     * @var string
     * @access  private
     */
    private $_keywords = '';

    /**
     *  Array of linked links.
     *
     * @var array
     * @access  private
     */
    private $_links = [];

    /**
     * Document base URL.
     *
     * @var string
     * @access  private
     */
    private $_base = '';

    /**
     * Document base URL.
     *
     * @var string
     * @access  private
     */
    private $_basetarget = '_self';

    /**
     * Array of linked scripts.
     *
     * @var array
     * @access   private
     */
    private $_scripts = [];

    /**
     * Array of scripts placed in the header.
     *
     * @var array
     * @access   private
     */
    private $_script = [];

    /**
     * Array of linked style sheets.
     *
     * @var array
     * @access  private
     */
    private $_styleSheets = [];

    /**
     * Array of included style declarations.
     *
     * @var array
     * @access  private
     */
    private $_style = [];

    /**
     * Array of meta tags.
     *
     * @var array
     * @access  private
     */
    private $_metaTags = [];

    private $_custom = [];
    /**
     * Contains the document language setting.
     *
     * @var string
     * @access  private
     */
    private $_language = 'en';

    /**
     * Contains the document direction setting.
     *
     * @var string
     * @access  private
     */
    private $_direction = 'ltr';

     /**
      * Document generator.
      *
      * @var     string
      * @access  private
      */
     private $_generator = 'Speedwork';

    /**
     * Contains the character encoding string.
     *
     * @var string
     * @access  private
     */
    private $_charset = 'utf-8';

    /**
     * Document mime type.
     *
     * @var string
     * @access  private
     */
    private $_mime = 'text/html';

    /**
     * set the full of the script or styles.
     *
     * @var string
     */
    public static $path;

    public $_author             = '';
    public $_copyright          = '';
    public $_robots             = 'index,follow';
    public $_cachecontrol       = 'max-age=30';

    /**
     * Add breadCrumb item to breadCrumb.
     *
     * @param string $text Text of the bread crumb
     * @param string $href url for bread crumb
     *
     * @return bool Retuns true on success and false on fail
     */
    public function breadCrumbItem($text, $href = '')
    {
        if ($text) {
            $this->breadcrumbs[] = ['href' => $href,'text' => $text];
        }

        return $this;
    }

    /**
     * Ganerate bread crumb from items added from breadCrumbItem method.
     *
     * @param string $separator Item seperator. default space
     *
     * @return string Generated bread crumb
     */
    public function breadCrumb($separator = '&nbsp;')
    {
        $files = [];
        if (is_array($this->breadcrumbs)) {
            foreach ($this->breadcrumbs as $file) {
                if (!$file['text']) {
                    continue;
                }
                $href = '';
                if ($file['href']) {
                    $href = (strpos($file['href'], 'link:') !== false) ?
                                        Router::link(str_replace('link:', '', $file['href'])) : $file['href'];
                }

                $files[] = '<span>'.(($href) ? ' <a href="'.$href.'"> ' : '').$file['text'].(($href) ? '</a>' : ' ').'</span>';
            }
        }

        if (count($files)  == 0) {
            return false;
        }

        return implode($separator, $files);
    }

    /**
     * Used to include component javascript files.
     *
     * @param string $component Name of the component
     * @param string $file      Javascript file name
     * @param array  $attr      Any custom attributes
     */
    public function addComponentScript($component, $file, $attr = [])
    {
        $component = Utility::sanitizeOption($component);
        $url       = Application::Url($component);
        $path      = $url.'components/'.$component.'/assets/'.$file;
        self::addScriptUrl($path, $attr);

        return $this;
    }

    /**
     * This function is defined to include javascript files of the modules.
     *
     * @params string $module, string $incJSFile, bool $head(default false)
     * @ return NULL
     * $head is used to tell the script include js files in footer section of the template or header section
     **/
    public function addModuleScript($module, $file, $attr = [])
    {
        $url    = Application::Url($module, 'module');
        $path   = $url.'modules/'.$module.'/assets/'.$file;
        self::addScriptUrl($path, $attr);

        return $this;
    }

    /**
     * Used to include javascript files from path, default active theme will be used.
     *
     * @param string $filename Javascript file name.
     * @param string $path     Path of the file.
     * @param array  $attr     Any custom attributes.
     *
     * @return boolen True on success and false on fail.
     */
    public function script($filename, $path = '', $attr = [])
    {
        if ($path == 'bower') {
            $path = _PUBLIC.'static/';
        }
        $path = (!$path) ? _TMP_URL.'js/' : $path;
        self::addScriptUrl($path.$filename, $attr);

        return $this;
    }

    /**
     * Used to include style sheets from path, default active theme will be used.
     *
     * @param string $filename Name of the style sheet.
     * @param string $path     Path of the file.
     * @param array  $attribs  Any custom attiributes for stylesheet.
     *
     * @return bool True on success and false on fail
     */
    public function styleSheet($filename, $path = '', $attribs = [])
    {
        if ($path == 'bower') {
            $path = _PUBLIC.'static/';
        }

        $path = (!$path) ? _TMP_URL.'css/' : $path;
        $path = $path.$filename;
        self::addStyleSheetUrl($path, $attribs);

        return $this;
    }

    /**
     * Adds a linked script to the page.
     *
     * @param string $url  URL to the linked script
     * @param string $type Type of script. Defaults to 'text/javascript'
     * @access   public
     */
    public function addScriptUrl($url, $attribs = [], $type = 'text/javascript')
    {
        $position = ($attribs['position']) ? $attribs['position'] : 'footer';
        if (is_array($attribs)) {
            unset($attribs['position']);
        }

        $this->_scripts[$position][$url]['mime']    = $type;
        $this->_scripts[$position][$url]['attribs'] = $attribs;

        return $this;
    }

    /**
     * Adds a linked stylesheet to the page.
     *
     * @param string $url   URL to the linked style sheet
     * @param string $type  Mime encoding type
     * @param string $media Media type that this stylesheet applies to
     * @access   public
     */
    public function addStyleSheetUrl($url, $attribs = [], $media = null, $type = 'text/css')
    {
        $this->_styleSheets[$url]['mime']       = $type;
        $this->_styleSheets[$url]['media']      = $media;
        $this->_styleSheets[$url]['attribs']    = $attribs;

        return $this;
    }

    /**
     * Adds a linked script to the page.
     *
     * @param string $filename
     * @param string $type     Type of script. Defaults to 'text/javascript'
     * @access   public
     */
    public function addScript($filename, $attribs = [], $type = 'text/javascript')
    {
        $url = self::$path.$filename;
        self::addScriptUrl($url, $attribs, $type);

        return $this;
    }

    /**
     * Adds a linked stylesheet to the page.
     *
     * @param string $filename
     * @param string $type     Mime encoding type
     * @param string $media    Media type that this stylesheet applies to
     * @access   public
     */
    public function addStyleSheet($filename, $attribs = [], $media = null, $type = 'text/css')
    {
        $url = self::$path.$filename;
        self::addStyleSheetUrl($url, $attribs, $media, $type);

        return $this;
    }

    /**
     * Adds a script to the page.
     *
     * @access   public
     *
     * @param string $content Script
     * @param string $type    Scripting mime (defaults to 'text/javascript')
     */
    public function addScriptDeclaration($content, $options = [], $type = 'text/javascript')
    {
        $position = ($options['position']) ? $options['position'] : 'footer';
        $this->_script[$position][strtolower($type)] .= chr(13).$content."\n";

        return $this;
    }

    /**
     * Adds a stylesheet declaration to the page.
     *
     * @param string $content Style declarations
     * @param string $type    Type of stylesheet (defaults to 'text/css')
     * @access   public
     */
    public function addStyleDeclaration($content, $type = 'text/css')
    {
        $this->_style[strtolower($type)] .= chr(13).$content."\n";

        return $this;
    }

    /**
     * Adds a custom html string to the head block.
     *
     * @param string The html to add to the head
     * @access   public
     */
    public function addCustomTag($html, $position = 'footer')
    {
        $this->_custom[$position][] = trim($html);

        return $this;
    }

    /**
     * Gets a meta tag.
     *
     * @param string $name       Value of name or http-equiv tag
     * @param bool   $http_equiv META type "http-equiv" defaults to null
     *
     * @return string
     * @access  public
     */
    public function getMetaData($name, $http_equiv = false)
    {
        $result = '';
        $name   = strtolower($name);
        if ($name == 'generator') {
            $result = $this->getGenerator();
        } elseif ($name == 'description') {
            $result = $this->getDescription();
        } else {
            if ($http_equiv == true) {
                $result = @$this->_metaTags['http-equiv'][$name];
            } else {
                $result = @$this->_metaTags['standard'][$name];
            }
        }

        return $result;
    }

    /**
     * Sets or alters a meta tag.
     *
     * @param string $name       Value of name or http-equiv tag
     * @param string $content    Value of the content tag
     * @param bool   $http_equiv META type "http-equiv" defaults to null
     * @access public
     */
    public function setMetaData($name, $content, $http_equiv = false)
    {
        $name = strtolower($name);
        if ($name == 'generator') {
            $this->setGenerator($content);
        } elseif ($name == 'description') {
            $this->setDescription($content);
        } else {
            if ($http_equiv === true) {
                $this->_metaTags['http-equiv'][$name] = $content;
            } elseif ($http_equiv != false) {
                $this->_metaTags[$http_equiv][$name] = $content;
            } else {
                $this->_metaTags['standard'][$name] = $content;
            }
        }

        return $this;
    }

    /**
     * Sets the document charset.
     *
     * @param string $type Charset encoding string
     * @access  public
     */
    public function setCharset($type = 'utf-8')
    {
        $this->_charset = $type;
    }

    /**
     * Returns the document charset encoding.
     *
     * @access public
     *
     * @return string
     */
    public function getCharset()
    {
        return $this->_charset;
    }

    /**
     * Sets the global document language declaration. Default is English (en-gb).
     *
     * @access public
     *
     * @param string $lang
     */
    public function setLanguage($lang = 'en-gb')
    {
        $this->_language = strtolower($lang);
    }

    /**
     * Returns the document language.
     *
     * @return string
     * @access public
     */
    public function getLanguage()
    {
        return $this->_language;
    }

    /**
     * Sets the global document direction declaration. Default is left-to-right (ltr).
     *
     * @access public
     *
     * @param string $lang
     */
    public function setDirection($dir = 'ltr')
    {
        $this->_direction = strtolower($dir);
    }

    /**
     * Returns the document language.
     *
     * @return string
     * @access public
     */
    public function getDirection()
    {
        return $this->_direction;
    }
    /**
     * Sets the base URI of the document.
     *
     * @param string $base
     * @access   public
     */
    public function setBase($base, $target = '_self')
    {
        if ($base != 'false' && $base != false) {
            $this->_base = $base;
        } else {
            $this->_base = null;
        }

        $this->_basetarget = $target;
    }

    /**
     * Return the base URI of the document.
     *
     * @return string
     * @access   public
     */
    public function getBase()
    {
        return $this->_base;
    }

    /**
     * Sets the description of the document.
     *
     * @param string $title
     * @access   public
     */
    public function setDescription($description)
    {
        $this->_description = $description;
    }

    /**
     * Return the title of the page.
     *
     * @return string
     * @access   public
     */
    public function getDescription()
    {
        return ($this->_description) ? $this->_description : Configure::read('app.descn');
    }

    /**
     * Sets the title of the document.
     *
     * @param string $title
     * @access   public
     */
    public function setTitle($title)
    {
        $this->_title = $title;
    }

    /**
     * Return the title of the document.
     *
     * @return string
     * @access   public
     */
    public function getTitle()
    {
        return ($this->_title) ? $this->_title : Configure::read('app.title');
    }

    /**
     * Sets the description of the document.
     *
     * @param string $title
     * @access   public
     */
    public function setKeywords($keywords)
    {
        $this->_keywords = $keywords;
    }

    /**
     * Return the title of the page.
     *
     * @return string
     * @access   public
     */
    public function getKeywords()
    {
        return ($this->_keywords) ? $this->_keywords : Configure::read('app.keywords');
    }

    /**
     * Sets the document generator.
     *
     * @param   string
     * @access  public
     */
    public function setGenerator($generator)
    {
        $this->_generator = $generator;
    }

    /**
     * Returns the document generator.
     *
     * @access public
     *
     * @return string
     */
    public function getGenerator()
    {
        return $this->_generator;
    }

    /**
     * Sets the document MIME encoding that is sent to the browser.
     *
     * <p>This usually will be text/html because most browsers cannot yet
     * accept the proper mime settings for XHTML: application/xhtml+xml
     * and to a lesser extent application/xml and text/xml. See the W3C note
     * ({@link http://www.w3.org/TR/xhtml-media-types/
     * http://www.w3.org/TR/xhtml-media-types/}) for more details.</p>
     *
     * @param string $type
     * @access   public
     */
    public function setMimeEncoding($type = 'text/html')
    {
        $this->_mime = strtolower($type);
    }

    /**
     * Adds <link> tags to the head of the document.
     *
     * <p>$relType defaults to 'rel' as it is the most common relation type used.
     * ('rev' refers to reverse relation, 'rel' indicates normal, forward relation.)
     * Typical tag: <link href="index.php" rel="Start"></p>
     *
     * @access   public
     *
     * @param string $href       The link that is being related.
     * @param string $relation   Relation of link.
     * @param string $relType    Relation type attribute.  Either rel or rev (default: 'rel').
     * @param array  $attributes Associative array of remaining attributes.
     */
    public function addHeadLink($href, $relation, $relType = 'rel', $attribs = [])
    {
        $attribs        = Utility::parseAttributes($attribs);
        $generatedTag   = '<link href="'.$href.'" '.$relType.'="'.$relation.'" '.$attribs;
        $this->_links[] = $generatedTag;

        return $this;
    }

    /**
     * Adds a shortcut icon (favicon).
     *
     * <p>This adds a link to the icon shown in the favorites list or on
     * the left of the url in the address bar. Some browsers display
     * it on the tab, as well.</p>
     *
     * @param string $href     The link that is being related.
     * @param string $type     File type
     * @param string $relation Relation of link
     * @access  public
     */
    public function addFavicon($href, $type = 'image/x-icon', $relation = 'shortcut icon')
    {
        $href           = str_replace('\\', '/', $href);
        $this->_links[] = '<link href="'.$href.'" rel="'.$relation.'" type="'.$type.'"';
    }

    public function beforeRender()
    {
        //check that is ajax request
        if (strtolower($this->data['REQUESTED_WITH']) == 'xmlhttprequest'
                                || strtolower($this->server['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
                                || ($this->type) || ($this->tpl) || ($this->format)) {
            $this->is_ajax_request = true;
            Registry::set('is_ajax_request', true);
            $this->assign('is_ajax_request', $this->is_ajax_request);
        }

        if (strtolower($this->request['REQUESTED_WITH']) == 'xmlhttprequest') {
            Registry::set('is_iframe_request', true);
            $this->assign('is_iframe_request', true);
        }

        //set device
        $device = Configure::read('device');
        if ($device['name']) {
            $this->_device = $device['name'];
        }

        $app = Configure::read('app');

        $this->setMimeEncoding();
        $this->setTitle($app['title']);
        $this->setBase($this->url);
        $this->setKeywords(trim($app['keywords']));
        $this->setDescription(trim($app['descn']));

        $prefix = Registry::get('url_prefix');
        //define global javascript var
        $html =  '<script type="text/javascript">';
        $html .= 'var is_user_logged_in = '.(($this->is_user_logged_in) ? ' true' : ' false').';';
        $html .= 'var url = "'.$this->format(_URL).'";';
        $html .= 'var base_url = "'.$this->format(rtrim(_URL.$prefix, '/')).'";';
        $html .= 'var public_url = "'._PUBLIC.'";';
        $html .= 'var theme_url = "'._TMP_URL.'";';
        $html .= 'var image_url = "'._IMG_URL.'";';
        $html .= 'var sys_url = "'.$this->format(_SYSURL).'";';
        $html .= 'var device = "'.$this->_device.'";';
        $html .= 'var _gaq = _gaq ||[];';
        $html .= 'var $_ = $_ || [];';
        $html .= 'var serverTime = '.(time() * 1000).';';
        $html .= '</script>';

        self::addCustomTag($html, 'header');

        $prefix = '?v=1905';

        self::addStyleSheetUrl(_SYSURL.'public/templates/system/css/core.css'.$prefix);

        self::addScriptUrl(_SYSURL.'public/templates/system/js/jquery.js'.$prefix, ['position' => 'header']);
        self::addScriptUrl(_SYSURL.'public/templates/system/js/migrate.js'.$prefix, ['position' => 'header']);

        self::addScriptUrl(_SYSURL.'public/templates/system/js/plugins.js'.$prefix);
        self::addScriptUrl(_SYSURL.'public/templates/system/js/plugins.min.js'.$prefix);
        self::addScriptUrl(_SYSURL.'public/templates/system/js/core.js'.$prefix);
        self::addScriptUrl(_SYSURL.'public/templates/system/js/system.js'.$prefix);
    }

    /**
     * Generates the head html and return the results as a string.
     *
     * @access public
     *
     * @return string
     */
    public function fetchHead()
    {
        // get line endings

        $lnEnd  = "\n";
        $tagEnd = ' />';
        $tab    = '';
        $html   = '';

        // Generate base tag (need to happen first)
        if ($this->getBase() || $this->_basetarget) {
            $html  .= $tab.'<base ';
            $html  .= ($this->getBase()) ? 'href="'.$this->format($this->getBase()).'" ' : ' ';
            $html  .= 'target="'.$this->_basetarget.'"'.$tagEnd.$lnEnd;
        }

        $append = Configure::read('app.append_sitename_title');
        $title  = htmlspecialchars($this->getTitle());

        if ($append == 'before') {
            $title = Configure::read('app.name').' | '.$title;
        }

        if ($append == 'after') {
            $title = $title.' | '.Configure::read('app.name');
        }

        $html .= $tab.'<title>'.$title.'</title>'.$lnEnd;
        $html .= $tab.'<meta name="description" content="'.htmlspecialchars($this->getDescription()).'"'.$tagEnd.$lnEnd;
        $html .= $tab.'<meta name="keywords" content="'.htmlspecialchars($this->getKeywords()).'" '.$tagEnd.$lnEnd;
        $html .= $tab.'<meta http-equiv="cache-control" content="'.$this->_cachecontrol.'"'.$tagEnd.$lnEnd;
        $html .= $tab.'<meta http-equiv="Content-Type" content="'.$this->_mime.'; charset='.$this->getCharset().'"'.$tagEnd.$lnEnd;
        $html .= $tab.'<meta name="generator" content="'.$this->_generator.'"'.$tagEnd.$lnEnd;
        $html .= $tab.'<meta name="author" content="'.$this->_author.'" '.$tagEnd.$lnEnd;
        $html .= $tab.'<meta name="copyright" content="'.$this->_copyright.'"'.$tagEnd.$lnEnd;
        $html .= $tab.'<meta name="robots" content="'.$this->_robots.'"'.$tagEnd.$lnEnd;

        // Generate META tags (needs to happen as early as possible in the head)
        foreach ($this->_metaTags as $type => $tag) {
            foreach ($tag as $name => $content) {
                if ($type == 'http-equiv') {
                    $html .= $tab.'<meta http-equiv="'.$name.'" content="'.$content.'"'.$tagEnd.$lnEnd;
                } elseif ($type == 'standard') {
                    $html .= $tab.'<meta name="'.$name.'" content="'.str_replace('"', "'", $content).'"'.$tagEnd.$lnEnd;
                } else {
                    $html .= $tab.'<meta  name="'.$name.'" '.$type.'="'.$name.'" content="'.str_replace('"', "'", $content).'"'.$tagEnd.$lnEnd;
                }
            }
        }

        // Generate link declarations
        foreach ($this->_links as $link) {
            $html .= $tab.$link.$tagEnd.$lnEnd;
        }

        // Generate stylesheet links
        foreach ($this->_styleSheets as $strSrc => $strAttr) {
            $html .= $tab.'<link rel="stylesheet" href="'.$this->format($strSrc).'" type="'.$strAttr['mime'].'"';
            if (!is_null($strAttr['media'])) {
                $html .= ' media="'.$strAttr['media'].'" ';
            }
            if ($temp =  Utility::parseAttributes($strAttr['attribs'])) {
                $html .= ' '.$temp;
            }
            $html .= $tagEnd.$lnEnd;
        }

        // Generate stylesheet declarations
        foreach ($this->_style as $type => $content) {
            $html .= $tab.'<style type="'.$type.'">'.$lnEnd;

            // This is for full XHTML support.
            if ($this->_mime == 'text/html') {
                $html .= $tab.$tab.'<!--'.$lnEnd;
            } else {
                $html .= $tab.$tab.'<![CDATA['.$lnEnd;
            }

            $html .= $content.$lnEnd;

            // See above note
            if ($this->_mime == 'text/html') {
                $html .= $tab.$tab.'-->'.$lnEnd;
            } else {
                $html .= $tab.$tab.']]>'.$lnEnd;
            }
            $html .= $tab.'</style>'.$lnEnd;
        }

        $html .= $this->renderScript('header');

        return $html;
    }

    public function format($url)
    {
        return str_replace('http://', '//', $url);
    }
    /**
     * Generates the footer html and return the results as a string.
     *
     * @access public
     *
     * @return string
     */
    public function fetchFooter()
    {
        return $this->renderScript('footer');
    }

    public function renderScript($position = 'footer')
    {
        $lnEnd  = "\n";
        $tab    = '';
        $html   = '';

        // Generate script file links
        if (is_array($this->_scripts[$position])) {
            foreach ($this->_scripts[$position] as $strSrc => $strAttr) {
                $html .= $tab.'<script type="'.$strAttr['mime'].'" src="'.$this->format($strSrc).'"';
                if ($temp =  Utility::parseAttributes($strAttr['attribs'])) {
                    $html .= ' '.$temp;
                }
                $html .= '></script>'.$lnEnd;
            }
        }

        // Generate script declarations
        if (is_array($this->_script[$position])) {
            foreach ($this->_script[$position] as $type => $content) {
                $html .= '<script type="'.$type.'">'.$lnEnd;

                // This is for full XHTML support.
                if ($this->_mime != 'text/html') {
                    $html .= '<![CDATA['.$lnEnd;
                }
                $html .= $tab.'jQuery(document).ready(function(){'.$lnEnd;
                $html .= $content.$lnEnd;
                $html .= $tab.'});'.$lnEnd;
                // See above note
                if ($this->_mime != 'text/html') {
                    $html .= '// ]]>'.$lnEnd;
                }
                $html .= '</script>'.$lnEnd;
            }
        }

        if (is_array($this->_custom[$position])) {
            foreach ($this->_custom[$position] as $custom) {
                $html .= $tab.$custom.$lnEnd;
            }
        }

        return $html;
    }

/******************** ---RENDERING PROCESS ---*****************************************/

    public function render($file)
    {
        if (is_file($file) && file_exists($file)) {
            $this->tengine->display($file);
        }
    }

    /**
     * Render and output the document template.
     *
     * @access public
     *
     * @param string $_template The template folder
     * @param string $file      | optional template file
     *
     * @return The parsed contents of the template
     */
    private function fetchTemplate($file = '')
    {
        $view = _TMP_VIEW;
        $view = ($view) ? $view : 'index';
        $file = $file ? $file : $view;
        $file = $file.$this->ext;

        $files = [
            _TMP_PATH.$file,
            _TMP_SYSTEM.'system'.DS.$file,
            _TMP_SYSTEM.'system'.DS.'index'.$this->ext,
        ];

        $template = '';
        foreach ($files as $file) {
            if (file_exists($file)) {
                $template =  $file;
                break;
            }
        }

        $tpl = true;
        if ($tpl) {
            $output = $this->tengine->fetch($template);
        } else {
            ob_start();
            include $template;
            $output = ob_get_contents();
            ob_end_clean();
        }
        echo $this->parseTemplate($output);
    }

    public function renderTemplate($file = '')
    {
        $res = $this->onBeforeRenderTemplate();
        if (!$res) {
            return false;
        }
        $this->assign('is_ajax_request', $this->is_ajax_request);
        $this->fetchTemplate($file);
    }

    private function onBeforeRenderTemplate()
    {
        if ($this->get['allowme']) {
            $_SESSION['allowme'] = $this->get['allowme'];
        }

        $allow = $_SESSION['allowme'];
        $key   = Configure::read('offline.key');
        //check whether this site is in offline
        if (Configure::read('offline.is_offline') && (empty($allow) || $allow != $key)) {
            $this->fetchTemplate('offline');

            return false;
        }

        // default allow to every one
        $allowed   = $this->userinfo->isAllowed($this->option, $this->view, $this->task);

        if (!$allowed && $this->is_ajax_request) {
            if ($this->type == 'html' || $this->format = 'html') {
                echo  'Your don\'t have sufficient permissions..';
            } else {
                echo json_encode(['status' => 'ERROR', 'message' => 'Your don\'t have sufficient permissions..']);
            }

            return false;
        }

        //for gusets
        if (!$allowed && !$this->is_user_logged_in) {
            $link   = 'index.php?option=com_members&view=login';
            Utility::redirect(Router::link($link));

            return false;
        }

        //for already loggedin users
        if (!$allowed && $this->is_user_logged_in) {
            echo '<div class="info_msg_err">Your don\'t have sufficient permissions.. </div>';
            Utility::redirect(Router::link('index.php?option=errors&view=nopermission'));

            return false;
        }

        //check that is ajax request
        if ($this->is_ajax_request) {
            $this->renderAjax();

            return false;
        }

        return true;
    }

    public function renderAjax()
    {
        $formats = ['raw', 'xml', 'rss', 'json', 'js', 'jsonp', 'script'];

        if ($this->format == 'js' || $this->format == 'script') {
            header('Content-Type: application/javascript');
        }

        if ($this->format == 'jsonp') {
            header('Content-Type: application/json');
        }

        if ($this->format == 'xml') {
            header('Content-Type: text/xml');
        }

        if ($this->type == 'module' && empty($this->format)) {
            return $this->application->module($this->option, $this->view);
        }

        if ($this->type == 'widget') {
            return $this->application->widget($this->option, [], true);
        }

        if ($this->type == 'captcha') {
            //If the type for request is captcha server it.
            App::uses('Securimage', 'library/Utility');
            $captcha     = new Securimage();
            $captcha->show();

            return true;
        }

        if ($this->type == 'html' || $this->format == 'html') {
            $file = 'html';

            return $this->fetchTemplate($file);
        }

        if ($this->type == 'page') {
            $response         = $this->application->loadController($this->option, $this->view);
            $response['html'] = $this->application->loadView($this->option, $this->view);

            echo json_encode($response);

            return true;
        }

        if (($this->type) || ($this->format) || ($this->tpl)) {
            if ((!$this->format)) {
                $file = ($this->tpl) ? str_replace('..', '', $this->tpl) : 'component';

                return $this->fetchTemplate($file);
            }

            if (in_array($this->type, $formats) || in_array($this->format, $formats)) {
                if ($this->type == 'module') {
                    $opts     = ['showtitle' => false];
                    $response = $this->application->module($this->option, $this->view, $opts);
                } else {
                    $response = $this->application->loadController($this->option, $this->view);
                }

                if ($this->format == 'json' || $this->format == 'jsonp') {
                    if (!is_array($response)) {
                        $response = $this->release('status');
                    }

                    if ($response && is_array($response)) {
                        $redirect = Registry::get('redirect');

                        if ($redirect) {
                            $response['redirect'] = $redirect;
                        }
                        if ($this->is_iframe_request) {
                            echo '<textarea>';
                            echo json_encode($response);
                            echo '</textarea>';
                        } else {
                            echo json_encode($response);
                        }
                    }
                }
            }

            return true;
        }

        $response = $this->application->loadController($this->option, $this->view);
        if (is_array($response)) {
            foreach ($response as $key => $value) {
                $this->assign($key, $value);
            }
        }

        $view_file  = _TMP_SYSTEM.'system'.DS.'ajax'.$this->ext;
        $this->render($view_file);
    }

    /**
     * Parse a document template.
     *
     * @access public
     *
     * @param string $data The data too parse
     *
     * @return The parsed contents of the template
     */
    protected function parseTemplate($data)
    {
        $replace = [];
        $matches = [];
        if (preg_match_all('#<speed:include type="([^"]+)" (.*)\/>#iU', $data, $matches)) {
            foreach ($matches[1] as $k => $v) {
                if ($v == 'header') {
                    unset($matches[1][$k]);
                    $matches[1][98] = $v;
                    $matches[2][98] = $matches[2][$k];
                    unset($matches[2][$k]);
                }
                if ($v == 'footer') {
                    unset($matches[1][$k]);
                    $matches[1][99] = $v;
                    $matches[2][99] = $matches[2][$k];
                    unset($matches[2][$k]);
                }
                if ($v == 'component') {
                    unset($matches[1][$k]);
                    $matches[1][0] = $v;
                    $matches[2][0] = $matches[2][$k];
                    unset($matches[2][$k]);
                }
            }

            ksort($matches[1]);
            $matches[1] = array_values($matches[1]);
            ksort($matches[2]);
            $matches[2] = array_values($matches[2]);

            $count = count($matches[1]);

            $matche = [];

            for ($i = 0; $i < $count; $i++) {
                $attribs = $this->parseAttributes($matches[2][$i]);
                $type    = $matches[1][$i];

                $name        = isset($attribs['name']) ? $attribs['name'] : null;
                $replace[$i] = $this->getBuffer($type, $name, $attribs);

                $matche[$i]   = '<speed:include type="'.$type.'" '.$matches[2][$i].'/>';
            }

            $data = str_replace($matche, $replace, $data);
        }

        return $data;
    }

    /**
     * Get the contents of a document include.
     *
     * @access public
     *
     * @param string $type    The type of renderer
     * @param string $name    The name of the element to render
     * @param array  $attribs Associative array of remaining attributes.
     *
     * @return The output of the renderer
     */
    protected function getBuffer($type = null, $name = null, $attribs = [])
    {
        $result = null;
        if (empty($type)) {
            return [];
        }

        $func = 'render'.ucfirst($type);
        ob_start();
        echo $this->$func($name, $attribs);
        $result =   ob_get_contents();
        ob_end_clean();

        return $result;
    }

    protected function renderModule($name, $attribs)
    {
        return $this->application->module($name, $attribs['view'], $attribs);
    }

    protected function renderModules($position)
    {
        if (empty($position)) {
            return;
        }

        return $this->application->modules($position);
    }

    protected function renderPosition($position, $attribs = [])
    {
        if (empty($position)) {
            return;
        }

        $modules = Configure::read('theme_modules');
        if (!is_array($modules) || !isset($modules[$position])) {
            return;
        }

        $modules = $modules[$position];

        foreach ($modules as $module) {
            $attribs = array_merge($attribs, $module);
            if ($module['type'] == 'component') {
                $this->renderComponent($module['option'], $attribs);
            } else {
                $this->renderModule($module['option'], $attribs);
            }
        }
    }

    protected function renderComponent($option, $attribs)
    {
        $option = ($option) ? $option : $this->option;
        $view   = ($attribs['view']) ? $attribs['view'] : $this->view;

        unset($attribs['name'], $attribs['view']);
        $response = $this->application->loadController($option, $view, $attribs);
        if (is_array($response)) {
            foreach ($response as $key => $value) {
                $this->assign($key, $value);
            }
        }

        return $this->application->loadView($option, $view);
    }

    protected function renderHeader()
    {
        $header = $this->fetchHead();

        if ($header) {
            $app = Configure::read('app');
            if ($app['minify_css'] || $app['minify_js']) {
                $minify = $this->application->helper('minify');
            }

            if ($app['minify_css']) {
                $header  = $minify->minifyStyles($header);
            }

            if ($app['minify_js']) {
                $header  = $minify->minifyScript($header, ['header' => true]);
            }
        }

        return $header;
    }

    protected function renderFooter()
    {
        $footer = $this->fetchFooter();

        if ($footer) {
            $app = Configure::read('app');
            if ($app['minify_css'] || $app['minify_js']) {
                $minify = $this->application->helper('minify');
            }

            if ($app['minify_css']) {
                $footer  = $minify->minifyStyles($footer);
            }

            if ($app['minify_js']) {
                $footer  = $minify->minifyScript($footer);
            }
        }

        return $footer;
    }

    /**
     * Method to extract key/value pairs out of a string with xml style attributes.
     *
     * @param string $string String containing xml style attributes
     *
     * @return array Key/Value pairs for the attributes
     */
    public function parseAttributes($string)
    {
        //Initialize variables
        $attr       = [];
        $retarray   = [];

        // Lets grab all the key/value pairs using a regular expression
        preg_match_all('/([\w:-]+)[\s]?=[\s]?"([^"]*)"/i', $string, $attr);

        if (is_array($attr)) {
            $numPairs = count($attr[1]);
            for ($i = 0; $i < $numPairs; $i++) {
                $retarray[$attr[1][$i]] = $attr[2][$i];
            }
        }

        return $retarray;
    }
}
