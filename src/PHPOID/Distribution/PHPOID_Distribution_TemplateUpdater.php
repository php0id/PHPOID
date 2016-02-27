<?php
/**
 * @copyright  <a href="http://php0id.web-box.ru/" target="_blank">php0id</a>
 * @package    Distribution
 * @subpackage Template
 * @license    https://opensource.org/licenses/mit-license.php
 * @version    {$Id}
 */

/**
 * Amiro.CMS templates updater.
 *
 * Example:
 * <code>
 * // "install_after.php" / "install.php" context
 *
 * $tplStorage = AMI::getResource('storage/tpl');
 * $updater = new PHPOID_Distribution_TemplateUpdater(
 *     $tplStorage,
 *     $this->oArgs->modId
 * );
 * $template = "templates/someTemplate.tpl";
 * $updater->load($template);
 *
 * $prependix = <<<EOT
 * <h1>This code will be added to the begining of set</h1>
 * EOT;
 * $updater->patch(
 *     PHPOID_Distribution_TemplateUpdater::PATCH_TYPE_ADD,
 *     PHPOID_Distribution_TemplateUpdater::PATCH_METHOD_TO_BEGINING,
 *     array('setName1', 'setName2),
 *     $prependix
 * );
 *
 * $appenix = <<<EOT
 * <h1>This code will be added to the end of set</h1>
 * EOT;
 * $updater->patch(
 *     PHPOID_Distribution_TemplateUpdater::PATCH_TYPE_ADD,
 *     PHPOID_Distribution_TemplateUpdater::PATCH_METHOD_TO_END,
 *     array('setName1', 'setName2),
 *     $appenix
 * );
 *
 * $insertion = <<<EOT
 * <h1>This code will be added once before searching string</h1>
 * EOT;
 * $updater->patch(
 *     PHPOID_Distribution_TemplateUpdater::PATCH_TYPE_ADD,
 *     PHPOID_Distribution_TemplateUpdater::PATCH_METHOD_BEFORE,
 *     array('setName1', 'setName2),
 *     $insertion,
 *     'searching string'
 * );
 *
 * $insertion = <<<EOT
 * <h1>This code will be added once after searching string</h1>
 * EOT;
 * $updater->patch(
 *     PHPOID_Distribution_TemplateUpdater::PATCH_TYPE_ADD,
 *     PHPOID_Distribution_TemplateUpdater::PATCH_METHOD_AFTER,
 *     array('setName1', 'setName2),
 *     $insertion,
 *     'searching string'
 * );
 *
 * $replacement = <<<EOT
 * <h1>This code will replace all searching strings</h1>
 * EOT;
 * $updater->patch(
 *     PHPOID_Distribution_TemplateUpdater::PATCH_TYPE_ADD,
 *     PHPOID_Distribution_TemplateUpdater::PATCH_METHOD_REPLACE,
 *     array('setName1', 'setName2),
 *     $replacement,
 *     'searching string'
 * );
 *
 * $replacement = <<<EOT
 * <h1>This code will replace searching string (searching string will be
 * processed as regular expression)</h1>
 * EOT;
 * $updater->patch(
 *     PHPOID_Distribution_TemplateUpdater::PATCH_TYPE_ADD,
 *     PHPOID_Distribution_TemplateUpdater::PATCH_METHOD_REPLACE_REG,
 *     array('setName1', 'setName2),
 *     $replacement,
 *     '/reqular expression/'
 * );
 *
 * $updater->save($template);
 *
 *
 * // "uninstall_before.php" / "uninstall_after.php" / "uninstall.php" /
 * // "uninstall_all.php" context
 *
 * // Next call will rollback all changes by marker
 * $tplStorage = AMI::getResource('storage/tpl');
 * $updater = new PHPOID_Distribution_TemplateUpdater(
 *     $tplStorage,
 *     $this->oArgs->modId
 * );
 * $template = "templates/someTemplate.tpl";
 * $updater->load($template);
 * $updater->rollback();
 *
 * $updater->save($template);
 * </code>
 *
 * @package    Distribution
 * @subpackage Template
 * @link       https://github.com/php0id/PHPOID/tree/master
 * @todo       Implement action on fail, multiple insertion on
 *             self::PATCH_METHOD_BEFORE & self::PATCH_METHOD_AFTER,
 *             preg_replace_callback() usage possibility on
 *             self::PATCH_METHOD_REG_REPLACE.
 */
class PHPOID_Distribution_TemplateUpdater
{
    const VERSION = '0.1';

    const ERR_CANNOT_LOAD          = 1;
    const ERR_CANNOT_BACKUP        = 2;
    const ERR_CANNOT_SAVE          = 3;
    const ERR_INVALID_MARKER_TYPE  = 4;
    const ERR_INVALID_MARKER_POS   = 5;
    const ERR_INVALID_PATCH_TYPE   = 6;
    const ERR_INVALID_PATCH_METHOD = 7;

    const MARKER_POS_OPENING = 'opening';
    const MARKER_POS_CLOSING = 'closing';
    const MARKER_TYPE_ADD    = 'add';
    const MARKER_TYPE_DELETE = 'delete';

    const PATCH_TYPE_ADD           = 'add';
    const PATCH_TYPE_DELETE        = 'delete';
    const PATCH_METHOD_TO_BEGINING = 'add_to_begininig';
    const PATCH_METHOD_TO_END      = 'add_to_end';
    const PATCH_METHOD_BEFORE      = 'add_before';
    const PATCH_METHOD_AFTER       = 'add_after';
    const PATCH_METHOD_REPLACE     = 'replace';
    const PATCH_METHOD_REG_REPLACE = 'reg_replace';

    /**
     * @var string
     */
    protected $caseSensetive = '';

    /**
     * @var bool
     */
    protected $checkDuplicate = TRUE;

    /**
     * @var AMI_iStorage
     */
    protected $storage;

    /**
     * @var string
     */
    protected $marker;

    /**
     * @var string
     */
    protected $re;

    /**
     * @var string
     */
    protected $contents;

    /**
     * @var bool
     */
    protected $changed;

    /**
     * @var array
     */
    protected $sets;

    /**
     * Constructor.
     *
     * @param AMI_iStorage $storage
     * @param string       $marker  Marker used to rollback patch
     */
    public function __construct(AMI_iStorage $storage, $marker)
    {
        $this->storage = $storage;
        $this->marker = $marker;
        $this->re =
            '/(<!--#set +(GS|GD)?var=")(.+?)"(\s+filter="(.*?)")?' .
            '(\s+value=")(.*?)("\\s*-->)([\\r]?[\\n]?)/s' .
            $this->caseSensetive;
    }

    /**
     * Sets checking for duplicate patching.
     *
     * @param  bool $checkDuplicate
     * @return void
     */
    public function setDuplicatesChecking($checkDuplicate)
    {
        $this->checkDuplicate = (bool)$checkDuplicate;
    }

    /**
     * Sets raw template contents.
     *
     * @param  string $contents  Template contents
     * @return void
     */
    public function setRawContents($contents)
    {
        $this->contents = $contents;
        $this->changed = FALSE;
    }

    /**
     * Returns template raw contents.
     *
     * @return string
     */
    public function getRawContents()
    {
        return $this->contents;
    }

    /**
     * Loads template contents.
     *
     * @param  string $template  Template name
     * @return void
     * @throws RuntimeExeption  Possible code is {@see self::ERR_CANNOT_LOAD}
     */
    public function load($template)
    {
        $this->contents = $this->storage->load($template);
        $this->sets = NULL;
        $this->changed = FALSE;
        if (FALSE === $this->contents) {
            $this->contents = NULL;
            throw new RuntimeExeption(
                "Template '{$template}' not found",
                self::ERR_CANNOT_LOAD
            );
        }
    }

    /**
     * Saves template contents.
     *
     * @param  string $template  Template name
     * @param  string $backup    Backup template name
     * @return void
     * @throws RuntimeExeption  If cannot backup or save
     */
    public function save($template, $backup = '')
    {
        if ($this->changed) {
            if ('' !== $backup) {
                if (!$this->storage->rename($template, $backup)){
                    throw new RuntimeExeption(
                        "Cannot backup template '{$template}' to '{$backup}'",
                        self::ERR_CANNOT_BACKUP
                    );
                }
            }
            if (!$this->storage->save($template, $this->contents)) {
                throw new RuntimeExeption(
                    "Cannot save template '{$template}'",
                    self::ERR_CANNOT_SAVE
                );
            }
        }
    }

    /**
     * Returns sets by passed name
     *
     * @param  string $name  Set name, '' for all
     * @return array
     */
    public function getSetsByName($name = '')
    {
        if (is_null($this->sets)) {
            $this->parseSets();
        }

        $result = array();
        if ('' !== $name) {
            foreach ($this->sets as $contents) {
                if (preg_match($this->re, $contents, $matches)) {
                    $sets = explode(";", $matches[3]);
                    if (in_array(
                        $name,
                        array_map('trim', $sets)
                    )) {
                        $result[] = $contents;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param  string $contents
     * @return array
     */
    public function parseSet($contents)
    {
        $result = array(
            'sets'     => array(),
            'contents' => NULL,
        );
        if (preg_match($this->re, $contents, $matches)) {
            $result = array(
                'sets'     => array_map('trim', explode(';', $matches[3])),
                'contents' => $matches[7],
            );
        }

        return $result;
    }

    /**
     * Sets set contents.
     *
     * @param string $name      Set name
     * @param string $contents
     */
    public function setContents($name, $contents)
    {
        foreach ($this->sets as $index => $prevContents) {
            if (preg_match($this->re, $prevContents, $matches)) {
                $sets = explode(";", $matches[3]);
                if (
                    in_array($name, array_map('trim', $sets)) &&
                    $matches[7] !== $contents
                ) {
                    $this->sets[$index] = str_replace(
                        $matches[7],
                        $contents,
                        $this->sets[$index]
                    );
                    $this->contents = str_replace(
                        $prevContents,
                        $this->sets[$index],
                        $this->contents
                    );
                    $this->changed = TRUE;
                }
            }
        }
    }

    /**
     * Returns marker for future template rollback.
     *
     * @param  string $position  {@see self::MARKER_POS_OPENING} or
     *                           {@see self::MARKER_POS_CLOSING}
     * @param  string $type      {@see self::MARKER_TYPE_ADD} or
     *                           {@see self::MARKER_TYPE_DELETE}
     * @return string
     * @throws InvalidArgumentException  Possible codes are
     *         {@see self::ERR_INVALID_MARKER_POS},
     *         {@see self::ERR_INVALID_MARKER_TYPE}
     */
    public function getMarker($position, $type)
    {
        $marker = '##--%s PHPOID[';
        switch ($type) {
            case self::MARKER_TYPE_ADD:
                $marker .= 'added';
                break;
            case self::MARKER_TYPE_DELETE:
                $marker .= 'deleted';
                break;
            default:
                throw new InvalidArgumentException(
                    "Invalid marker type '{$type}' passed",
                    self::ERR_INVALID_MARKER_TYPE
                );
        }
        $marker .= "] {$this->marker}%s";
        switch ($position) {
            case self::MARKER_POS_OPENING:
                $marker = sprintf($marker, '', ' {');
                break;
            case self::MARKER_POS_CLOSING:
                $marker = sprintf($marker, ' }', '');
                break;
            default:
                throw new InvalidArgumentException(
                    "Invalid marker position '{$position}' passed",
                    self::ERR_INVALID_MARKER_POS
                );
        }
        $marker .= ' --##';
        if (self::MARKER_TYPE_DELETE == $type) {
            switch ($position) {
                case self::MARKER_POS_OPENING:
                    $marker .= '##--';
                    break;
                case self::MARKER_POS_CLOSING:
                    $marker = "--##{$marker}";
                    break;
            }
        }

        return $marker;
    }

    /**
     * Patch sets.
     *
     * @param  string $type      {@see self::PATCH_TYPE_ADD} or
     *                           {@see self::PATCH_TYPE_DELETE}
     * @param  string $method    {@see self::PATCH_METHOD_TO_BEGINING},
     *                           {@see self::PATCH_METHOD_TO_END},
     *                           {@see self::PATCH_METHOD_BEFORE},
     *                           {@see self::PATCH_METHOD_AFTER},
     *                           {@see self::PATCH_METHOD_REPLACE} or
     *                           {@see self::PATCH_METHOD_REG_REPLACE}
     * @param  array  $names     Array of set names
     * @param  string $contents
     * @param  string $pattern   Search pattern
     * @return void
     * @throws InvalidArgumentException  Possible codes are
     *         {@see self::ERR_INVALID_PATCH_TYPE},
     *         {@see self::ERR_INVALID_PATCH_METHOD}
     */
    public function patch(
        $type, $method, array $names,
        $contents = FALSE, $pattern = FALSE
    )
    {
        $types = array(
            self::PATCH_TYPE_ADD    => self::MARKER_TYPE_ADD,
            self::PATCH_TYPE_DELETE => self::MARKER_TYPE_DELETE,
        );
        if (!in_array($type, $types)) {
            throw new InvalidArgumentException(
                "Invalid patch type '{$type}' passed",
                self::ERR_INVALID_PATCH_TYPE
            );
        }
        if (
            self::MARKER_TYPE_DELETE == $type &&
            in_array($method, array(
                self::PATCH_METHOD_TO_BEGINING,
                self::PATCH_METHOD_TO_END,
                self::PATCH_METHOD_BEFORE,
                self::PATCH_METHOD_AFTER,
            ))
        ) {
            throw new InvalidArgumentException(
                "Invalid patch method '{$type}' " .
                "using type '{$type}' passed",
                self::ERR_INVALID_PATCH_METHOD
            );
        }

        $opening = $this->getMarker(self::MARKER_POS_OPENING, $type);
        $closing = $this->getMarker(self::MARKER_POS_CLOSING, $type);
        if (in_array(
            $method,
            array(self::PATCH_METHOD_REPLACE, self::PATCH_METHOD_REG_REPLACE)
        )) {
            $prevOpening = $this->getMarker(
                self::MARKER_POS_OPENING, self::MARKER_TYPE_DELETE
            );
            $prevClosing = $this->getMarker(
                self::MARKER_POS_CLOSING, self::MARKER_TYPE_DELETE
            );
        }

        foreach ($names as $name) {
            $sets = '*' != $name
                ? $this->getSetsByName($name)
                : array($this->contents);
            $setContents = TRUE;
            foreach ($sets as $prevContents) {
                $parsed = '*' != $name
                    ? $this->parseSet($prevContents)
                    : array(
                        'sets' => array('*'),
                        'contents' => $this->contents
                    );
                switch ($method) {
                    case self::PATCH_METHOD_TO_BEGINING:
                        $prependix = $opening . $contents . $closing; // ;)
                        if (
                            $this->checkDuplicate &&
                            0 === mb_strpos($parsed['contents'], $prependix)
                        ) {
                            break;
                        }
                        $parsed['contents'] = $prependix . $parsed['contents'];
                        break; // case self::PATCH_METHOD_TO_BEGINING

                    case self::PATCH_METHOD_TO_END:
                        $appendix = $opening . $contents . $closing;
                        if (
                            $this->checkDuplicate && mb_substr(
                                $parsed['contents'],
                                -mb_strlen($appendix)
                            ) === $appendix
                        ) {
                            break;
                        }
                        $parsed['contents'] .= $appendix;
                        break; // case self::PATCH_METHOD_TO_END

                    case self::PATCH_METHOD_BEFORE:
                        $insertion = $opening . $contents . $closing;
                        $pos = mb_strpos($parsed['contents'], $pattern);
                        if (FALSE === $pos) {
                            break;
                        }
                        $length = mb_strlen($insertion);
                        if (
                            $this->checkDuplicate &&
                            $pos >= $length &&
                            mb_substr(
                                $parsed['contents'],
                                $pos - $length,
                                $length
                            ) === $insertion
                        ) {
                            // Duplicate
                            break;
                        }
                        $parsed['contents'] =
                            mb_substr($parsed['contents'], 0, $pos) .
                            $insertion .
                            mb_substr($parsed['contents'], $pos);
                        break; // case self::PATCH_METHOD_BEFORE

                    case self::PATCH_METHOD_AFTER:
                        $insertion = $opening . $contents . $closing;
                        $pos = mb_strpos($parsed['contents'], $pattern);
                        if (FALSE === $pos) {
                            break;
                        }
                        $length = mb_strlen($insertion);
                        $patternLength = mb_strlen($pattern);
                        if (
                            $this->checkDuplicate &&
                            mb_strlen($parsed['contents']) >=
                            $pos + $patternLength + $length &&
                            mb_substr(
                                $parsed['contents'],
                                $pos + $patternLength,
                                $length
                            ) === $insertion
                        ) {
                            // Duplicate
                            break;
                        }
                        $pos += $patternLength;
                        $parsed['contents'] =
                            mb_substr($parsed['contents'], 0, $pos) .
                            $insertion .
                            mb_substr($parsed['contents'], $pos);
                        break; // case self::PATCH_METHOD_AFTER

                    case self::PATCH_METHOD_REPLACE:
                        $offset = 0;
                        do {
                            $pos = mb_strpos($parsed['contents'], $pattern, $offset);
                            if (FALSE === $pos) {
                                // Not found
                                break;
                            }
                            $length = mb_strlen($prevOpening);
                            if (
                                $this->checkDuplicate && (
                                    $pos > ($length - 1) &&
                                    $prevOpening === mb_substr(
                                        $parsed['contents'],
                                        $pos - $length,
                                        $length
                                    )
                                )
                            ) {
                                // Already commented
                                $offset += $pos + mb_strlen(
                                    self::MARKER_TYPE_ADD == $type
                                        ? $prevOpening . $pattern . $prevClosing
                                        : ''

                                );
                                continue;
                            }
                            $replacement = (
                                self::MARKER_TYPE_ADD == $type
                                    ? $prevOpening . $pattern . $prevClosing
                                    : ''
                            ) . $opening . $contents . $closing;
                            $parsed['contents'] =
                                str_replace(
                                    $pattern,
                                    $replacement,
                                    $parsed['contents']
                                );
                            $offset +=
                                $pos + mb_strlen($replacement) -
                                mb_strlen($pattern);
                        } while (TRUE);

                        break; // case self::PATCH_METHOD_REPLACE

                    case self::PATCH_METHOD_REG_REPLACE:
                        if (preg_match($pattern, $parsed['contents'], $matches)) {
                            $this->patch(
                                $type,
                                self::PATCH_METHOD_REPLACE,
                                array($name),
                                $contents,
                                $matches[0]
                            );
                            $setContents = FALSE;
                        }
                        break; // case self::PATCH_METHOD_REG_REPLACE

                    default:
                        throw new InvalidArgumentException(
                            "Invalid patch method '{$method}' passed",
                            self::ERR_INVALID_PATCH_METHOD
                        );
                }
                if ($setContents) {
                    if ('*' != $name) {
                        $this->setContents($name, $parsed['contents']);
                    } else if ($parsed['contents'] !== $this->contents) {
                        $this->contents = $parsed['contents'];
                        $this->changed = TRUE;
                    }
                }
            }
        }
    }

    /**
     * Rollbacks patch.
     *
     * @return void
     */
    public function rollback()
    {
        $contents = $this->contents;
        // Delete added parts
        $opening = preg_quote($this->getMarker(
            self::MARKER_POS_OPENING, self::MARKER_TYPE_ADD
        ), '/');
        $closing = preg_quote($this->getMarker(
            self::MARKER_POS_CLOSING, self::MARKER_TYPE_ADD
        ), '/');
        $this->contents =
            preg_replace(
                "/{$opening}.*?{$closing}/s{$this->caseSensetive}",
                '\\1\\2',
                $this->contents
            );

        // Restore deleted parts
        $opening = preg_quote($this->getMarker(
            self::MARKER_POS_OPENING, self::MARKER_TYPE_DELETE
        ), '/');
        $closing = preg_quote($this->getMarker(
            self::MARKER_POS_CLOSING, self::MARKER_TYPE_DELETE
        ), '/');
        $this->contents =
            preg_replace(
                array(
                    "/{$opening}/s{$this->caseSensetive}",
                    "/{$closing}/s{$this->caseSensetive}",
                ),
                '',
                $this->contents
            );

        if ($contents !== $this->contents) {
            $this->parseSets();
            $this->changed = TRUE;
        }
    }

    /**
     * @return void
     */
    protected function parseSets()
    {
        $this->sets =
            preg_match_all($this->re, $this->contents, $matches)
                ? $matches[0]
                : array();
    }
}
