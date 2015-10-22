<?php

namespace DnD\Bundle\MagentoConnectorBundle\Writer\File;

use Symfony\Component\Validator\Constraints as Assert;
use Akeneo\Bundle\BatchBundle\Item\ItemWriterInterface;
use Akeneo\Bundle\BatchBundle\Item\AbstractConfigurableStepElement;
use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Akeneo\Bundle\BatchBundle\Step\StepExecutionAwareInterface;
use Akeneo\Bundle\BatchBundle\Job\RuntimeErrorException;
use Pim\Bundle\ImportExportBundle\Validator\Constraints\WritableDirectory;
use DnD\Bundle\MagentoConnectorBundle\Helper\SFTPConnection;

/**
 *
 * @author    DnD Mimosa <mimosa@dnd.fr>
 * @copyright Agence Dn'D (http://www.dnd.fr)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class FileWriter extends AbstractConfigurableStepElement implements
    ItemWriterInterface,
    StepExecutionAwareInterface
{
    /**
     * @Assert\NotBlank(groups={"Execution"})
     * @WritableDirectory(groups={"Execution"})
     */
    protected $filePath = '/tmp/export_%datetime%.csv';

    /**
     * @var StepExecution
     */
    protected $stepExecution;

    private $handler;

    private $resolvedFilePath;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $port;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $remoteFilePath;

    /**
     * Set the host of the SFTP Connection
     *
     * @param string $delimiter
     */
    public function setHost($host)
    {
        $this->host = $host;
    }

    /**
     * Get the port of the SFTP Connection
     *
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Set the port of the SFTP Connection
     *
     * @param string $delimiter
     */
    public function setPort($port)
    {
        $this->port = $port;
    }

    /**
     * Get the port of the SFTP Connection
     *
     * @return string
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Set the username of the SFTP Connection
     *
     * @param string $delimiter
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * Get the username of the SFTP Connection
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set the password of the SFTP Connection
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * Get the password of the SFTP Connection
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Set the remote file path
     *
     * @return string
     */
    public function setRemoteFilePath($remoteFilePath)
    {
        $this->remoteFilePath = $remoteFilePath;
    }

    /**
     * Get the remote file path
     *
     * @return string
     */
    public function getRemoteFilePath()
    {
        return $this->remoteFilePath;
    }

    /**
     * Set the file path
     *
     * @param string $filePath
     *
     * @return FileWriter
     */
    public function setFilePath($filePath)
    {
        $this->filePath = $filePath;
        $this->resolvedFilePath = null;

        return $this;
    }

    /**
     * Get the file path
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Get the file path in which to write the data
     *
     * @return string
     */
    public function getPath()
    {
        if (!isset($this->resolvedFilePath)) {
            $this->resolvedFilePath = strtr(
                $this->filePath,
                array(
                    '%datetime%' => date('Y-m-d_H:i:s')
                )
            );
        }

        return $this->resolvedFilePath;
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $data)
    {
        if (!$this->handler) {
            $path = $this->getPath();
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            $this->handler = fopen($path, 'w');
        }

        foreach ($data as $entry) {
            if (false === fwrite($this->handler, $entry)) {
                throw new RuntimeErrorException('Failed to write to file %path%', ['%path%' => $this->getPath()]);
            } else {
                $this->stepExecution->incrementSummaryInfo('write');
            }
        }
    }

    public function flush()
    {
        if(!empty($this->getHost() && !empty($this->getPort()))){
          $sftpConnection = new SFTPConnection($this->getHost(), $this->getPort());
          $sftpConnection->login($this->getUsername(), $this->getPassword());
          if(file_exists($this->getPath())){
              $sftpConnection->uploadFile($this->getPath(), $this->getRemoteFilePath());
          }
        }
    }

    /**
     * Close handler when desctructing the current instance
     */
    public function __destruct()
    {
        if ($this->handler) {
            fclose($this->handler);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return array(
            'filePath' => array(
                'options' => array(
                    'label' => 'pim_base_connector.export.filePath.label',
                    'help'  => 'pim_base_connector.export.filePath.help'
                )
            ),
            'host' => array(
                'options' => array(
                    'label'    => 'dnd_magento_connector.export.host.label',
                    'help'     => 'dnd_magento_connector.export.host.help',
                    'required' => true
                )
            ),
            'port' => array(
                'options' => array(
                    'label'    => 'dnd_magento_connector.export.port.label',
                    'help'     => 'dnd_magento_connector.export.port.help',
                    'required' => true
                )
            ),
            'username' => array(
                'options' => array(
                    'label'    => 'dnd_magento_connector.export.username.label',
                    'help'     => 'dnd_magento_connector.export.username.help',
                    'required' => true
                )
            ),
            'password' => array(
                'options' => array(
                    'label'    => 'dnd_magento_connector.export.password.label',
                    'help'     => 'dnd_magento_connector.export.password.help',
                    'required' => true
                )
            ),
            'remoteFilePath' => array(
                'options' => array(
                    'label'    => 'dnd_magento_connector.export.remoteFilePath.label',
                    'help'     => 'dnd_magento_connector.export.remoteFilePath.help',
                    'required' => true
                )
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }
}
