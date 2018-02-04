<?php

namespace Cuatromedios\Kusikusi\Http\Controllers\Api;

use Cuatromedios\Kusikusi\Exceptions\ExceptionDetails;
use Cuatromedios\Kusikusi\Http\Controllers\Controller;
use Cuatromedios\Kusikusi\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Config;
use Cuatromedios\Kusikusi\Models\Http\ApiResponse;

class EntityController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Return the specified entity.
     *
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function getOne($id, Request $request)
    {
        try {
            $lang = $request->input('lang', Config::get('general.langs')[0]);
            $fields = $request->input('fields', []);
            $entity = Entity::getOne($id, $fields, $lang);
            if (Gate::allows('get-entity', $id)) {
                return (new ApiResponse($entity, TRUE))->response();
            } else {
                return (new ApiResponse(NULL, FALSE, ApiResponse::TEXT_UNAUTHORIZED, ApiResponse::STATUS_UNAUTHORIZED))->response();
            }
        } catch (\Exception $e) {
            return (new ApiResponse(NULL, FALSE, ExceptionDetails::filter($e), ApiResponse::STATUS_NOTFOUND))->response();
        }
    }

    /**
     * Create the specified entity.
     *
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function post(Request $request)
    {
        try {
            // TODO: Filter the json to delete al not used data
            $id = Entity::post($request->json()->all());
            return (new ApiResponse($id, TRUE))->response();
        } catch (\Exception $e) {
            $status = 400;
            return (new ApiResponse(NULL, FALSE, ExceptionDetails::filter($e), $status))->response();
        }
    }

    /**
     * Update the specified entity.
     *
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function patch($id, Request $request)
    {
        try {
            // TODO: Filter the json to delete al not used data
            $id = Entity::patch($id, $request->json()->all());
            return (new ApiResponse($id, TRUE))->response();

        } catch (\Exception $e) {
            $status = 400;
            return (new ApiResponse(NULL, FALSE, ExceptionDetails::filter($e), $status))->response();
        }
    }

    /**
     * Display entity's parent.
     *
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function getParent($id, Request $request)
    {
        try {
            $lang = $request->input('lang', Config::get('general.langs')[0]);
            $fields = $request->input('fields', []);
            $entity = Entity::getParent($id, $fields, $lang);
            if (!$entity instanceof Entity) {
                return $this->sendNotFoundResponse("The entity with id {$id} doesn't exist");
            }
            return (new ApiResponse($entity, TRUE))->response();
        } catch (\Exception $e) {
            $status = 404;
            return (new ApiResponse(NULL, FALSE, ExceptionDetails::filter($e), $status))->response();
        }
    }

    /**
     * Display all entities.
     *
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function get(Request $request)
    {
        try {
            $fields = $request->input('fields', []);
            $lang = $request->input('lang', Config::get('general.langs')[0]);
            $order = $request->input('order', NULL);
            $collection = Entity::get(NULL, $fields, $lang, $order);
            return (new ApiResponse($collection, TRUE))->response();
        } catch (\Exception $e) {
            $status = 500;
            return (new ApiResponse(NULL, FALSE, ExceptionDetails::filter($e), $status))->response();
        }
    }

    /**
     * Display entity's children.
     *
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function getChildren($id, Request $request)
    {
        try {
            if (Gate::allows('get-entity', $id)) {
                $fields = $request->input('fields', []);
                $lang = $request->input('lang', Config::get('general.langs')[0]);
                $order = $request->input('order', NULL);
                $collection = Entity::getChildren($id, $fields, $lang, $order);
                return (new ApiResponse($collection, TRUE))->response();
            } else {
                return (new ApiResponse(NULL, FALSE, ApiResponse::TEXT_UNAUTHORIZED, ApiResponse::STATUS_UNAUTHORIZED))->response();
            }
        } catch (\Exception $e) {
            $status = 500;
            return (new ApiResponse(NULL, FALSE, ExceptionDetails::filter($e), $status))->response();
        }
    }

    /**
     * Display entity's ancestors.
     *
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function getAncestors($id, Request $request)
    {
        try {
            $fields = $request->input('fields', []);
            $lang = $request->input('lang', Config::get('general.langs')[0]);
            $order = $request->input('order', NULL);
            $collection = Entity::getAncestors($id, $fields, $lang, $order);
            return (new ApiResponse($collection, TRUE))->response();
        } catch (\Exception $e) {
            $status = 500;
            return (new ApiResponse(NULL, FALSE, ExceptionDetails::filter($e), $status))->response();
        }
    }

    /**
     * Display entity's ancestors.
     *
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function getDescendants($id, Request $request)
    {
        try {
            $fields = $request->input('fields', []);
            $lang = $request->input('lang', Config::get('general.langs')[0]);
            $order = $request->input('order', NULL);
            $collection = Entity::getDescendants($id, $fields, $lang, $order);
            return (new ApiResponse($collection, TRUE))->response();
        } catch (\Exception $e) {
            $status = 500;
            return (new ApiResponse(NULL, FALSE, ExceptionDetails::filter($e), $status))->response();
        }
    }

    /**
     * Display entity's relations.
     *
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function getRelations($id, $kind = NULL, Request $request)
    {
        try {
            $fields = $request->input('fields', []);
            $lang = $request->input('lang', Config::get('general.langs')[0]);
            $order = $request->input('order', NULL);
            $collection = Entity::getEntityRelations($id, $kind, $fields, $lang, $order);
            return (new ApiResponse($collection, TRUE))->response();
        } catch (\Exception $e) {
            $status = 500;
            return (new ApiResponse(NULL, FALSE, ExceptionDetails::filter($e), $status))->response();
        }
    }
    /**
     * Display entity's relations.
     *
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function getInverseRelations($id, $kind = NULL, Request $request)
    {
        try {
            $fields = $request->input('fields', []);
            $lang = $request->input('lang', Config::get('general.langs')[0]);
            $order = $request->input('order', NULL);
            $collection = Entity::getInverseEntityRelations($id, $kind, $fields, $lang, $order);
            return (new ApiResponse($collection, TRUE))->response();
        } catch (\Exception $e) {
            $status = 500;
            return (new ApiResponse(NULL, FALSE, ExceptionDetails::filter($e), $status))->response();
        }
    }
}
