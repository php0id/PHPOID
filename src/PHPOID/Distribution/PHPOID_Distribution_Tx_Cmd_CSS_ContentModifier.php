<?php
/**
 * @copyright <a href="http://php0id.web-box.ru/" target="_blank">php0id</a>
 * @package   Distribution
 * @license   https://opensource.org/licenses/mit-license.php
 * @version   {$Id}
 */

/**
 * CSS-file content modifier.
 *
 * @package    Distribution
 * @subpackage TxCommand
 * @link       https://github.com/php0id/PHPOID/tree/master
 * @see
 */
abstract class PHPOID_Distribution_Tx_Cmd_CSS_ContentModifier
extends AMI_Tx_Cmd_Storage_ContentModifier
{
    const VERSION = '1.0';

    /**
     * @inheritdoc
     */
    protected function createNewContent()
    {
        // Create declaration file if not exists
        $content = '';
        trigger_error(
            "Missing CSS file at '{$this->oArgs->target}', creating new one"
        );

        return $content;
    }

    /**
     * @inheritdoc
     */
    protected function getOpeningMarker()
    {
        return
            '/* Do not delete this comment! [' .
            $this->oArgs->modId . '] { */' . $this->eol;
    }

    /**
     * @inheritdoc
     */
    protected function getClosingMarker()
    {
        return
            '/* } Do not delete this comment! [' .
            $this->oArgs->modId . '] */' . $this->eol;
    }
}

/**
 * Transaction command adding CSS-file content.
 *
 * Example:
 * <code>
 * // "install_after.php" / "install.php" context
 *
 * $fsStorage = AMI::getResource('storage/fs');
 * $srcPath = dirname(__FILE__) . '/stuff';
 *
 * // Patch CSS files
 * class_exists('PHPOID_Tx_Cmd_CSS_ContentModifier');
 * $this->aTx['storage']->addCommandResources(
 *     array(
 *         'css/install'   => 'tx/cmd/css/install',
 *         'css/uninstall' => 'tx/cmd/css/uninstall',
 *     )
 * );
 *
 * $destPath = AMI_Registry::get('path/root') . '_mod_files/_css';
 * $source = 'ami_custom.addon.css';
 * $target = 'ami_custom.css';
 * $args = new AMI_Tx_Cmd_Args(
 *     array(
 *         // Instance Id
 *         'modId'    => $this->oArgs->modId,
 *         // Installation mode
 *         'mode'     => $this->oArgs->mode,
 *         // Source file path
 *         'source'   => "{$srcPath}/{$file}",
 *         // Target file to patch
 *         'target'   => "{$destPath}/{$file}",
 *         // Storage driver
 *         'oStorage' => $fsStorage,
 *     )
 * );
 * $this->aTx['storage']->addCommand('css/install', $args);
 *
 * // File 'ami_custom.addon.css' contains CSS-template:
 * body * {
 *     background: green;
 * }
 * </code>
 *
 * @package    Distribution
 * @subpackage TxCommand
 * @resource   tx/cmd/css/install
 *             <code>AMI::getResource('tx/cmd/css/install')</code>
 */
class PHPOID_Distribution_Tx_Cmd_CSS_ContentIntsall extends PHPOID_Distribution_Tx_Cmd_CSS_ContentModifier{
    /**
     * @inheritdoc
     */
    protected $aObligatoryArgs = array('source');

    /**
     * @inheritdoc
     */
    protected function validateArgs()
    {
        $this->validateObligatoryArgs($this->aObligatoryArgs);

        parent::validateArgs();
    }

    /**
     * @inheritdoc
     */
    protected function init(){
        // To avoid throwing exception on existent file
        $this->oArgs->overwrite(
            'mode',
            $this->oArgs->mode |
            AMI_iTx_Cmd::MODE_IGNORE_TARGET_EXISTENCE
        );

        parent::init();
    }
    /**
     * @inheritdoc
     */
    protected function modify(&$content, $opener, $closer)
    {
        $code = $this->oStorage->load($this->oArgs->source);
        if($code === FALSE){
            throw new AMI_Tx_Exception(
                "Missing CSS template at '" . $this->oArgs->source . "'",
                AMI_Tx_Exception::CMD_MISSING_TPL_CONTENT
            );
        }
        $aArgs = array_filter(
            $this->oArgs->getAll(),
            array($this, 'cbFilterObjects')
        );
        $code = str_replace(
            array_map(
                array($this, 'cbToTplVar'),
                array_keys($aArgs)
            ),
            array_values($aArgs),
            $code
        );
        $content .= $opener . $code . $closer;
    }

    /**
     * Callback filterring objects from arguments.
     *
     * @param  mixed $value  Value
     * @return bool
     * @see    self::modify()
     */
    protected function cbFilterObjects($value)
    {
        return !is_object($value);
    }

    /**
     * Callback converting key to template variable name.
     *
     * @param  string $key  Name
     * @return string
     * @see    self::modify()
     */
    protected function cbToTplVar($key)
    {
        return '##' . $key . '##';
    }
}

/**
 * Transaction command deleting part of CSS-file content.
 *
 * Example:
 * <code>
 * // "uninstall_before.php" / "uninstall_after.php" / "uninstall.php" /
 * // "uninstall_all.php" context
 *
 * $fsStorage = AMI::getResource('storage/fs');
 *
 * // Patch CSS files
 *
 * class_exists('PHPOID_Tx_Cmd_CSS_ContentModifier');
 * $this->aTx['storage']->addCommandResources(
 *     array(
 *         'css/install'   => 'tx/cmd/css/install',
 *         'css/uninstall' => 'tx/cmd/css/uninstall',
 *     )
 * );
 *
 * $destPath = AMI_Registry::get('path/root') . '_mod_files/_css';
 * $target = 'ami_custom.css';
 * $args = new AMI_Tx_Cmd_Args(
 *     array(
 *         // Unnstallation mode
 *         'mode'      => $this->oArgs->mode,
 *         // Instance Id
 *         'modId'     => $this->oArgs->modId,
 *         // Target file path
 *         'target'    => "{$destPath}/{$file}",
 *         // Storage driver
 *         'oStorage'  => $fsStorage,
 *     )
 * );
 * $this->aTx['storage']->addCommand('css/uninstall', $args);
 * </code>
 *
 * @package    Distribution
 * @subpackage TxCommand
 * @resource   tx/cmd/css/uninstall
 *             <code>AMI::getResource('tx/cmd/css/uninstall')</code>
 */
class PHPOID_Distribution_Tx_Cmd_CSS_ContentUninstall
extends PHPOID_Distribution_Tx_Cmd_CSS_ContentModifier
{
    /**
     * @inheritdoc
     */
    protected function validateArgs()
    {
        $this->validateObligatoryArgs(array('target'));

        parent::validateArgs();
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        // To avoid throwing exception on existent file
        $this->oArgs->overwrite(
            'mode',
            $this->oArgs->mode |
            AMI_iTx_Cmd::MODE_IGNORE_TARGET_EXISTENCE |
            AMI_iTx_Cmd::MODE_IGNORE_DATA_EXISTENCE
        );

        parent::init();
    }

    /**
     * @inheritdoc
     */
    protected function modify(&$content, $opener, $closer)
    {
        // Nothing to do to wipe out code.
    }
}

AMI::addResourceMapping(
    array(
        'tx/cmd/css/install'   =>
            'PHPOID_Distribution_Tx_Cmd_CSS_ContentIntsall',
        'tx/cmd/css/uninstall' =>
            'PHPOID_Distribution_Tx_Cmd_CSS_ContentUninstall',
    )
);
