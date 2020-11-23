<?
use Bitrix\Main;
use Bitrix\Main\Localization\Loc as Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Web\Uri;

class CityInterfaceComponent extends \CBitrixComponent
{

	protected $cacheKeys 	= array();
	protected $cacheAddon 	= array();
	protected $navParams 	= array();
	protected $returned;
	protected $tagCache;

	public function onIncludeComponentLang()
	{
		$this->includeComponentLang(basename(__FILE__));
		Loc::loadMessages(__FILE__);
	}

	/**
	 * проверяет подключение необходиимых модулей
	 */
	protected function checkModules()
	{
		if (!Main\Loader::includeModule('iblock'))
			throw new Main\LoaderException(Loc::getMessage('IBLOCK_MODULE_NOT_INSTALLED'));
		if (!Main\Loader::includeModule('sale'))
			throw new Main\LoaderException(Loc::getMessage('SALE_MODULE_NOT_INSTALLED'));
	}

	/**
	 * проверяет заполнение обязательных параметров
	 */
	protected function checkParams()
	{
		if ($this->arParams['IBLOCK_ID'] <= 0)
			throw new Main\ArgumentNullException('IBLOCK_ID');
	}

	/**
	 * выполяет действия перед кешированием
	 */
	protected function executeProlog()
	{
		if ($this->arParams['CITY_COUNT'] > 0) {
			if ($this->arParams['DISPLAY_TOP_PAGER'] || $this->arParams['DISPLAY_BOTTOM_PAGER']) {
				\CPageOption::SetOptionString('main', 'nav_page_in_session', 'N');
				$this->navParams = array(
					'nPageSize' => $this->arParams['CITY_COUNT']
				);
				$arNavigation = \CDBResult::GetNavParams($this->navParams);
				$this->cacheAddon = array($arNavigation);
			} else {
				$this->navParams = array(
					'nTopCount' => $this->arParams['CITY_COUNT']
				);
			}
		} else {
			$this->navParams = false;
		}
	}


	public function onPrepareComponentParams($params)
	{
		$result = array(
			'IBLOCK_TYPE' => trim($params['IBLOCK_TYPE']),
			'IBLOCK_ID' => intval($params['IBLOCK_ID']),
			'DISPLAY_TOP_PAGER' => $params['DISPLAY_TOP_PAGER'] == 'Y',
			'DISPLAY_BOTTOM_PAGER' => $params['DISPLAY_BOTTOM_PAGER'] == 'Y',
			'CITY_COUNT' => intval($params['CITY_COUNT']),
			'SORT_BY' => strlen($params['SORT_BY']) ? $params['SORT_BY'] : 'NAME',
			'SORT_ORDER' => $params['SORT_ORDER'] == 'ASC' ? 'ASC' : 'DESC',
			'CACHE_TIME' => intval($params['CACHE_TIME']) > 0 ? intval($params['CACHE_TIME']) : 3600,
			'AJAX' => $params['AJAX'] == 'N' ? 'N' : $_REQUEST['AJAX'] == 'Y' ? 'Y' : 'N',
			'FIELD_CODE' => is_array($params['FIELD_CODE']) && count($params['FIELD_CODE']) ? $params['FIELD_CODE'] : array(
				'ID',
				'NAME',
				'DATE_ACTIVE_FROM',
			),
		);
		return $result;
	}

	/**
	 * определяет читать данные из кеша или нет
	 */
	protected function readDataFromCache()
	{
		global $USER;
		if ($this->arParams['CACHE_TYPE'] == 'N')
			return false;

		if ($this->arParams['CACHE_GROUPS'] == 'Y') {
			if (is_array($this->cacheAddon)) {
				$this->cacheAddon[] = $USER->GetUserGroupArray();
			} else {
				$this->cacheAddon = array($USER->GetUserGroupArray());
			}
		}
		return !($this->startResultCache(false, $this->cacheAddon, md5(serialize($this->arParams))));
	}

	/**
	 * кеширует ключи массива arResult
	 */
	protected function putDataToCache()
	{
		if (is_array($this->cacheKeys) && sizeof($this->cacheKeys) > 0)
		{
			$this->SetResultCacheKeys($this->cacheKeys);
		}
	}

	/**
	 * прерывает кеширование
	 */
	protected function abortDataCache()
	{
		$this->AbortResultCache();
	}

	protected function deleteCity() {
		$request = Application::getInstance()->getContext()->getRequest();
		$DELETE_CITY = $request->getQuery('DELETE_CITY');
		$uriString = $request->getRequestUri();
		if($DELETE_CITY && is_numeric($DELETE_CITY)) {
			$filter = array(
				'IBLOCK_TYPE' => $this->arParams['IBLOCK_TYPE'],
				'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
				'=ID' => intval($DELETE_CITY),
			);
			$isCity = \CIBlockElement::GetList(array(), $filter, false, false, array('ID'))->Fetch();
			if($isCity) {
				\CIBlockElement::Delete($isCity['ID']);
				$this->abortDataCache();
				$uri = new Uri($uriString);
				$uri->deleteParams(array('DELETE_CITY'));
				LocalRedirect($uri->getUri());
			}

		}
	}

	/**
	 * заполнение ИБ городами
	 */
	protected function loadCity()
	{
		$el = new \CIBlockElement;
		$arrCity = array();
		$arCityFilter = array('!CITY_ID' => 'NULL', '>CITY_ID' => '0', 'COUNTRY_LID' => 'ru', 'CITY_LID' => 'ru');
		$rsLocCount = CSaleLocation::GetList(array('CITY_NAME'=>'ASC'), $arCityFilter, false, false, array('ID','CITY_NAME'));
		while($arr = $rsLocCount->Fetch()) {
			$arrCity[$arr['ID']] = $arr['CITY_NAME'];
		}
		$filter = array(
			'IBLOCK_TYPE' => $this->arParams['IBLOCK_TYPE'],
			'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
			'=EXTERNAL_ID' => array_keys($arrCity),
		);
		$issetCity = array();
		$iterator = \CIBlockElement::GetList(array(), $filter, false, false, array('ID','EXTERNAL_ID'));
		while ($element = $iterator->Fetch()) {
			$issetCity[$element['EXTERNAL_ID']] = $element['ID'];
		}
		foreach ($arrCity as $EXTERNAL_ID => $NAME) {
			if(!isset($issetCity[$EXTERNAL_ID])) {
				$arrAdd = array(
					'IBLOCK_TYPE' => $this->arParams['IBLOCK_TYPE'],
					'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
					'NAME' => $NAME,
					'ACTIVE' => 'Y',
					'EXTERNAL_ID' => $EXTERNAL_ID,
				);
				$el->Add($arrAdd);
			}
		}

	}

	/**
	 * получение результатов (разовый метод, служит только для единовременного добавления всех городов в ИБ)
	 */
	protected function getResult()
	{
		$filter = array(
			'IBLOCK_TYPE' => $this->arParams['IBLOCK_TYPE'],
			'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
			'ACTIVE' => 'Y'
		);
		$sort = array(
			$this->arParams['SORT_BY'] => $this->arParams['SORT_ORDER'],
		);
		$select = $this->arParams['FIELD_CODE'];
		if(!in_array('ID',$select)) $select[] = 'ID';
		if(!in_array('NAME',$select)) $select[] = 'NAME';
		$iterator = \CIBlockElement::GetList($sort, $filter, false, $this->navParams, $select);
		while ($element = $iterator->Fetch())
		{
			$this->arResult['ITEMS'][] = $element;
		}
		if (($this->arParams['DISPLAY_TOP_PAGER'] || $this->arParams['DISPLAY_BOTTOM_PAGER']) && $this->arParams['CITY_COUNT'] > 0)
		{
			$this->arResult['NAV_STRING'] = $iterator->GetPageNavString('');
		}
	}

	protected function getIblockId()
	{
		$this->arResult['IBLOCK_ID'] = $this->arParams['IBLOCK_ID'];
		$this->cacheKeys[] = 'IBLOCK_ID';
	}

	/**
	 * выполняет действия после выполения компонента
	 */
	protected function executeEpilog()
	{

	}

	public function executeComponent()
	{
		global $APPLICATION;
		try
		{
			$this->checkModules();
			$this->checkParams();
//			$this->loadCity();
			$this->deleteCity();
			$this->executeProlog();
			if ($this->arParams['AJAX'] == 'Y') $APPLICATION->RestartBuffer();
			if (!$this->readDataFromCache()) {
				$this->getIblockId();
				$this->getResult();
				$this->putDataToCache();
				$this->includeComponentTemplate();
			}
			$this->executeEpilog();

			if ($this->arParams['AJAX'] == 'Y') die();

			return $this->returned;
		}
		catch (Exception $e)
		{
			$this->abortDataCache();
			ShowError($e->getMessage());
		}
	}
}