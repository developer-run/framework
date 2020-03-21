<?php
/**
 * Class IRouterMappingProvider
 *
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 * @date: 17.03.2020
 */
namespace Devrun\Module\Providers;

interface IRouterMappingProvider
{

	/**
	 * Returns array of ServiceDefinition,
	 * that will be appended to setup of router service
	 *
	 * @return \Nette\Application\IRouter
	 */
	public function getRoutesDefinition();

}
