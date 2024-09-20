<?php

namespace Drupal\entity_json_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Clase controladora para el ejemplo de batch api.
 */
class ApiController extends ControllerBase {

    /**
     * The request stack.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $requestStack;



    /**
     * Constructor for ApiControllerBaseQueries.
     *
     *   The request stack.
     */
    public function __construct( $requestStack) {
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('request_stack')
        );
    }
    public function getEntityByAlias() {
        $content = $this->requestStack->getCurrentRequest()->getContent();
        $recursive = $this->requestStack->getCurrentRequest()->query->get('recursive', false);

        if (empty($content)) {
            return new JsonResponse(["error" => "No content provided"], 400);
        }

        $decode = json_decode($content, true);
    
        if (!isset($decode["alias"])) {
            return new JsonResponse(["error" => "Alias not provided"], 400);
        }

        $mod_alias = $decode["alias"];
        $lang_code = \Drupal::languageManager()->getCurrentLanguage()->getId();

        $mod_alias = "/alias/" . $lang_code . "" . $mod_alias . ".json";

        $file_alias = \Drupal::service("entity_json_speed.file")->getPath($mod_alias);
        if(!file_exists($file_alias)) {
            return new JsonResponse(["error" => "Entity not found"], 404);
        }

        $info_entity = json_decode(file_get_contents($file_alias), true);
        

        $file_entity_config = "/" . $info_entity["target_type"] . "/" . $info_entity["bundle"] . "/" . $info_entity["target_id"] . "/config.json";
        $file_entity_config = \Drupal::service("entity_json_speed.file")->getPath($file_entity_config);
        if(!file_exists($file_entity_config)) {
            return new JsonResponse(["error" => "Entity not found"], 404);
        }

        $file_entity_entity = "/" . $info_entity["target_type"] . "/" . $info_entity["bundle"] . "/" . $info_entity["target_id"] . "/". $lang_code . ".json";
        $file_entity_entity = \Drupal::service("entity_json_speed.file")->getPath($file_entity_entity);

        $entity = json_decode(file_get_contents($file_entity_entity), true);
        $config = json_decode(file_get_contents($file_entity_config), true);

        return new JsonResponse(["entity" =>$entity, "config"=> $config], 200);

    }

    public function getEntityById($entity_type, $id, $recursive) {

    }
}
