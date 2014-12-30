<?php
namespace TYPO3\Surf\CMS\Task;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3.Surf.CMS".*
 *                                                                        *
 *                                                                        */


use TYPO3\Surf\Exception\InvalidConfigurationException;
use TYPO3\Surf\Domain\Model\Node;
use TYPO3\Surf\Domain\Model\Application;
use TYPO3\Surf\Domain\Model\Deployment;

use TYPO3\Flow\Annotations as Flow;

/**
 * A generic shell task
 *
 */
class RsyncFoldersTask extends \TYPO3\Surf\Domain\Model\Task {

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Surf\Domain\Service\ShellCommandService
	 */
	protected $shell;

	/**
	 * Executes this task
	 *
	 * Options:
	 *   command: The command to execute
	 *   rollbackCommand: The command to execute as a rollback (optional)
	 *
	 * @param \TYPO3\Surf\Domain\Model\Node $node
	 * @param \TYPO3\Surf\Domain\Model\Application $application
	 * @param \TYPO3\Surf\Domain\Model\Deployment $deployment
	 * @param array $options
	 * @return void
	 * @throws \TYPO3\Surf\Exception\InvalidConfigurationException
	 */
	public function execute(Node $node, Application $application, Deployment $deployment, array $options = array()) {
		if (!isset($options['folders'])) {
			return;
		}
		$folders = $options['folders'];
		if (!is_array($folders)) {
			$folders = array($folders);
		}
		$replacePaths = array(
			'{deploymentPath}' => $application->getDeploymentPath(),
			'{sharedPath}' => $application->getSharedPath(),
			'{releasePath}' => $deployment->getApplicationReleasePath($application),
			'{currentPath}' => $application->getDeploymentPath() . '/releases/current',
			'{previousPath}' => $application->getDeploymentPath() . '/releases/previous'
		);

		$commands = array();

		$username = isset($options['username']) ? $options['username'] . '@' : '';
		$hostname = $node->getHostname();
		$port = $node->hasOption('port') ? '-P ' . escapeshellarg($node->getOption('port')) : '';

		foreach ($folders as $folderPair) {
			if (!is_array($folderPair) || count($folderPair) !== 2) {
				throw new InvalidConfigurationException('Each rsync folder definition must be an array of exactly two folders', 1405599056);
			}
			$sourceFolder = rtrim(str_replace(array_keys($replacePaths), $replacePaths, $folderPair[0]), '/') . '/';
			$targetFolder = rtrim(str_replace(array_keys($replacePaths), $replacePaths, $folderPair[1]), '/') . '/';
			$commands[] = "rsync -avz --delete -e ssh {$sourceFolder} {$username}{$hostname}:{$targetFolder}";
		}

		$ignoreErrors = isset($options['ignoreErrors']) && $options['ignoreErrors'] === TRUE;
		$logOutput = !(isset($options['logOutput']) && $options['logOutput'] === FALSE);

		$localhost = new Node('localhost');
		$localhost->setHostname('localhost');

		$this->shell->executeOrSimulate($commands, $localhost, $deployment, $ignoreErrors, $logOutput);
	}

	/**
	 * Simulate this task
	 *
	 * @param Node $node
	 * @param Application $application
	 * @param Deployment $deployment
	 * @param array $options
	 * @return void
	 */
	public function simulate(Node $node, Application $application, Deployment $deployment, array $options = array()) {
		$this->execute($node, $application, $deployment, $options);
	}
}
?>