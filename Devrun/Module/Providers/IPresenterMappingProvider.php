<?php
/**
 * Class IRouterMappingProvider
 *
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 * @date: 17.03.2020
 */

namespace Devrun\Module\Providers;


interface IPresenterMappingProvider
{

	/**
	 * Returns array of ClassNameMask => PresenterNameMask
	 *
	 * @example return array('*' => 'Booking\*Module\Presenters\*Presenter');
	 * @return array
	 */
	public function getPresenterMapping();
}