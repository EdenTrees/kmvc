<?php

namespace Kartaca\Kmvc;

use Kartaca\Kmvc\ModelView\RenderType as RenderType;

class Dispatcher
{
    /**
     * Application path where the Controller directory exists.
     *
     * @var string
     */
    protected $_appPath;
    
    /**
     * Default Namespace if nothing given it's \ by default that is none
     *
     * @var string
     */
    protected $_defaultNamespace;
    
    /**
     * Constructor
     *
     * @param string $appPath Application Path
     * @param string $defaultNamespace default namespace for the project, it's \ by default
     */
    public function __construct($appPath, $defaultNamespace = "\\")
    {
        $this->_appPath = $appPath;
        $this->_defaultNamespace = $defaultNamespace;
    }
    
    /**
     * Returns application path
     *
     * @return string
     */
    public function getAppPath()
    {
        return $this->_appPath;
    }
    
    /**
     * Returns the defaultnamespace
     *
     * @return string
     */
    public function getDefaultNamespace()
    {
        return $this->_defaultNamespace;
    }
    
    /**
     * Checks if a given route exists or not...
     *  Returns true or false based on the existence of the route
     *
     * @param string $route URL where the page is called.
     * @return boolean true if both controller and action exists
     */
    public function routeExists($route)
    {
        list($_controllerName, $_actionName) = $this->getControllerAndAction($route, $this->_defaultNamespace);
        return class_exists($_controllerName) && method_exists($_controllerName, $_actionName);
    }
    
    /**
     * Returns controller and action as an array.
     *  First being the controller class name and second being the action method name
     *
     * @param string $route URL where the page is called
     * @param string $defaultNamespace default namespace for the project
     * @return array
     */
    public static function getControllerAndAction($route, $defaultNamespace, $returnOptions = false)
    {
        $_dispatch = preg_split("/\//", $route);
        $_dispatch = array_map(function($_item) {
            return strtolower($_item);
        }, $_dispatch);
        $_moduleName = "";
        $_controllerName = $_dispatch[1];
        $_actionName = $_dispatch[2];
        if (preg_match("/_/", $_controllerName)) {
            list($_moduleName, $_controllerName) = preg_split("/_/", $_controllerName, 2);
        }
        $_fullActionName = strtolower($_actionName) . "Action";
        $_fullControllerName = $defaultNamespace
            . "\\"
            . ($_moduleName !== "" ? ucfirst($_moduleName) . "\\" : "" )
            . ucfirst($_controllerName)
            . "Controller";
        if (class_exists($_fullControllerName) && method_exists($_fullControllerName, $_fullActionName)) {
            $_result = array($_fullControllerName, $_fullActionName);
            if ($returnOptions) {
                $_result[] = array(
                    "module" => $_moduleName,
                    "controller" => $_controllerName,
                    "action" => $_actionName,
                );
            }
            return $_result;
        }
        return array("", "");
    }
    
    /**
     * Dispatches the request to the Controller's Action!
     *  Currently it just returns
     *
     * @param string $route URL that we are looking for something like music_index/index or index/index
     * @param string $options Options for the app
     * @return string
     */
    public static function dispatch($route, $options = null)
    {
        list($_controllerName, $_actionName, $_options) = self::getControllerAndAction($route, $options["defaultNamespace"], true);
        $_filePath = $options["appPath"];
        if (isset($_options["module"]) && !empty($_options["module"])) {
            $_filePath .= "/" . $_options["module"];
        }
        $_filePath .= "/views/" . $_options["controller"] . "/" . $_options["action"] . ".phtml";
        $_controller = new $_controllerName($_filePath);
        $_controller->$_actionName();
        if ($_controller->getView()->getRenderType() === RenderType::NONE) {
            return null;
        }
        //Check the render and intercept it if required...
        if (isset($_REQUEST["_f"]) && $_REQUEST["_f"] === "json") {
            $_controller->getView()->setRenderType(RenderType::JSON);
        } else if (isset($_REQUEST["_escaped_fragment_"])) {
            //TODO: This part might require a proper handling in the Drupal part so this might be a bit problematic...
            $_controller->getView()->setRenderType(RenderType::CRAWLER);
        }
        $_content = $_controller->getView()->render(); 
        if (null === $_content) {
            //If content is not passed then return null to intercept creation of the layout...
            return null;
        }
        return $_content;
        
    }
}