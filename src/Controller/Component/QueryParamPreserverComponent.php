<?php
namespace Psa\QueryParamPreserver\Controller\Component;

use Cake\Controller\Component;
use Cake\Routing\Router;

/**
 * QueryParamPreserverComponent
 *
 * @copyright 2016 PSA Publishers Ltd.
 * @license MIT
 */
class QueryParamPreserverComponent extends Component {

    /**
     * Default Config
     *
     * @var array
     */
    public $_defaultConfig = [
        'autoApply' => true,
        'actions' => [],
        'ignoreParams' => [],
        'disablePreserveWithParam' => 'preserve'
    ];

    /**
     * Checks if the query params should be preserved for the current action.
     *
     * @return bool
     */
    public function actionCheck()
    {
        return in_array($this->request->getParam('action'), $this->getConfig('actions'));
    }

    /**
     * Preserves the current query params
     *
     * @return void
     */
    public function preserve()
    {
        $query = $this->request->getQuery();
        $ignoreParams = $this->config('ignoreParams');
        if (!empty($ignoreParams)) {
            foreach ($ignoreParams as $param) {
                if (isset($query[$param])) {
                    unset($query[$param]);
                }
            }
        }

        $this->request->getSession()->write(
            $this->_hashKey(),
            $query
        );
    }

    /**
     * Builds the hash key for the current call
     *
     * @return string Hash key
     */
    protected function _hashKey()
    {
        $string = '';
        if (!empty($this->request->plugin)) {
            $string .= $this->request->plugin;
        }
        $string .= $this->request->controller . '.' . $this->request->action;
        return $string;
    }

    /**
     * Applies the preserved query params
     *
     * @return \Cake\Network\Response|null
     */
    public function apply()
    {
        $key = $this->_hashKey();
        if (empty($this->request->query) && $this->request->session()->check($key)) {
            $this->request->query = array_merge(
                (array)$this->request->session()->read($key),
                $this->request->query
            );
            $request = $this->_registry->getController()->request;
            if ($request->here !== Router::url(['?' => $this->request->query])) {
                return $this->_registry->getController()->redirect(['?' => $this->request->query]);
            };
        }
    }

    /**
     * beforeFilter callback
     *
     * @return \Cake\Network\Response|null
     */
    public function beforeFilter()
    {
        $params = $this->request->getQueryParams();
        $ignoreParam = $this->getConfig('disablePreserveWithParam');

        if ($this->getConfig('autoApply') && $this->actionCheck()) {
            if (isset($params[$ignoreParam])) {
                unset($params[$ignoreParam]);
                $this->request->session()->delete($this->_hashKey());
                $this->request = $this->request->withQueryParams($params);
                $this->getController()->redirect([
                    '?' => $params
                ]);
            }

            return $this->apply();
        }
    }

    /**
     * beforeRender callback
     *
     * @return void
     */
    public function beforeRender()
    {
        if ($this->getConfig('autoApply') && $this->actionCheck()) {
            $this->preserve();
        }
    }

}
