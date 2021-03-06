<?php
namespace Cuatromedios\Kusikusi\Http\Controllers\Api;

use App\Models\Entity;
use Cuatromedios\Kusikusi\Exceptions\ExceptionDetails;
use Cuatromedios\Kusikusi\Http\Controllers\Controller;
use Cuatromedios\Kusikusi\Models\Activity;
use Cuatromedios\Kusikusi\Models\Http\ApiResponse;
use Cuatromedios\Kusikusi\Providers\AuthServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

/**
 * Class EntityController
 *
 * @package Cuatromedios\Kusikusi\Http\Controllers\Api
 */
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
     * Display all entities.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function get(Request $request)
    {
        try {
            if (Gate::allows(AuthServiceProvider::READ_ALL)) {
                $lang = $request->input('lang', Config::get('general.langs')[0]);
                $permissions = Auth::user()->permissions;
                // TODO: Get every entity descendant of the user's 'home'
                $query = Entity::select();
                $query = process_querystring($query, $request);
                $entity = $query->get()->compact();
                Activity::add(Auth::user()['id'], '', AuthServiceProvider::READ_ENTITY, true, 'get', '{}');

                return (new ApiResponse($entity, true))->response();
            } else {
                Activity::add(Auth::user()['id'], '', AuthServiceProvider::READ_ENTITY, false, 'get',
                    json_encode(["error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], '', AuthServiceProvider::READ_ENTITY, false, 'get',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     * Return the specified entity.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function getOne($id, Request $request)
    {
        try {
            if (Gate::allows(AuthServiceProvider::READ_ENTITY, [$id, 'getOne', "{}"])) {
                $lang = $request->input('lang', Config::get('general.langs')[0]);
                $query = Entity::select();
                $query = process_querystring($query, $request);
                //TODO: Select attached data fields
                $entity = $query->find($id);
                if ($entity != null) {
                    $entity = $query->find($id)->compact();
                }
                Activity::add(Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, true, 'getOne', '{}');

                return (new ApiResponse($entity, true))->response();
            } else {
                Activity::add(Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getOne',
                    json_encode(["error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], '', AuthServiceProvider::READ_ENTITY, false, 'getOne',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     * Create the specified entity.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function post(Request $request)
    {
        try {
            if (Gate::allows(AuthServiceProvider::WRITE_ENTITY, [$request->parent_id]) === true) {
                $this->validate($request, [
                    "parent_id" => "required|string",
                    "model"     => "required|string",
                ], Config::get('validator.messages'));
                $body = $request->json()->all();
                $entityPosted = new Entity($body);
                $entityPosted->save();
                Activity::add(\Auth::user()['id'], $entityPosted['id'], AuthServiceProvider::WRITE_ENTITY, true, 'post',
                    json_encode(["body" => $body]));

                return (new ApiResponse($entityPosted, true))->response();
            } else {
                Activity::add(\Auth::user()['id'], '', AuthServiceProvider::WRITE_ENTITY, false, 'post',
                    json_encode(["body" => $request->json()->all(), "error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], '', AuthServiceProvider::WRITE_ENTITY, false, 'post',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     * Update the specified entity.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function patch($id, Request $request)
    {
        try {
            if (Gate::allows(AuthServiceProvider::WRITE_ENTITY, [$id]) === true) {
                // TODO: Filter the json to delete all not used data
                $body = $request->json()->all();
                $entityPatched = Entity::find($id)->update($body);
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::WRITE_ENTITY, true, 'patch',
                    json_encode(["body" => $body]));

                return (new ApiResponse($entityPatched, true))->response();
            } else {
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::WRITE_ENTITY, false, 'patch',
                    json_encode(["body" => $request->json()->all(), "error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], $id, AuthServiceProvider::WRITE_ENTITY, false, 'post',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     *
     * Makes a soft delete on the specified entity.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function softDelete($id)
    {
        try {
            if (Gate::allows(AuthServiceProvider::WRITE_ENTITY, [$id]) === true) {
                $entitySoftDeleted = Entity::find($id)->deleteEntity();
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::WRITE_ENTITY, true, 'softDelete', '{}');

                return (new ApiResponse($id, true))->response();
            } else {
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::WRITE_ENTITY, false, 'softDelete',
                    json_encode(["error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], $id, AuthServiceProvider::WRITE_ENTITY, false, 'softDelete',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     * Makes a hard deletes on the specified entity.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function hardDelete($id)
    {
        try {
            if (Gate::allows(AuthServiceProvider::WRITE_ENTITY, [$id]) === true) {
                $entitySoftDeleted = Entity::withTrashed()->find($id)->deleteEntity(true);
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::WRITE_ENTITY, true, 'hardDelete', '{}');

                return (new ApiResponse($id, true))->response();
            } else {
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::WRITE_ENTITY, false, 'hardDelete',
                    json_encode(["error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], $id, AuthServiceProvider::WRITE_ENTITY, false, 'hardDelete',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     * Display entity's parent.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function getParent($id, Request $request)
    {
        try {
            if (Gate::allows(AuthServiceProvider::READ_ENTITY, [$id, 'getParent', "{}"])) {
                $lang = $request->input('lang', Config::get('general.langs')[0]);
                $query = Entity::select();
                $query = process_querystring($query, $request);
                //TODO: Select attached data fields
                $entity = $query->parentOf($id)->get()->compact();
                if (count($entity) > 0) {
                    Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, true, 'getParent', '{}');

                    return (new ApiResponse($entity, true))->response();
                } else {
                    Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getParent',
                        json_encode(["error" => ApiResponse::TEXT_NOTFOUND]));

                    return (new ApiResponse(null, false, ApiResponse::TEXT_NOTFOUND,
                        ApiResponse::STATUS_NOTFOUND))->response();
                }
            } else {
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getParent',
                    json_encode(["error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getParent',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     * Display entity's parent.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function getTree($id, Request $request)
    {
        try {
            if (Gate::allows(AuthServiceProvider::READ_ENTITY, [$id, 'getTree', "{}"])) {
                $lang = $request->input('lang', Config::get('general.langs')[0]);
                $query = Entity::select()
                               ->descendantOf($id, 'asc', $request['depth'] ?? 999)
                ;
                $query = process_querystring($query, $request)
                    ->addSelect('entities.id', 'entities.parent_id');
                $entities = $query->get()->compact();
                $tree = self::buildTree($entities, $id);
                if (count($entities) > 0) {
                    Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, true, 'getParent', '{}');

                    return (new ApiResponse($tree, true))->response();
                } else {
                    Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getParent',
                        json_encode(["error" => ApiResponse::TEXT_NOTFOUND]));

                    return (new ApiResponse(null, false, ApiResponse::TEXT_NOTFOUND,
                        ApiResponse::STATUS_NOTFOUND))->response();
                }
            } else {
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getParent',
                    json_encode(["error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getParent',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     * @param array $entities
     * @param string $parent_id
     *
     * @return array
     */
    private static function buildTree(array &$entities, $parent_id = 'root')
    {
        $branch = [];
        foreach ($entities as &$entity) {
            if ($entity['parent_id'] == $parent_id) {
                $children = self::buildTree($entities, $entity['id']);
                if ($children) {
                    $entity['children'] = $children;
                }
                $branch[] = $entity;
                unset($entity);
            }
        }

        return $branch;
    }

    /**
     * Display entity's children.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function getChildren($id, Request $request)
    {
        try {
            if (Gate::allows(AuthServiceProvider::READ_ENTITY, [$id, 'getChildren', "{}"])) {
                $lang = $request->input('lang', Config::get('general.langs')[0]);
                $query = Entity::select();
                $query = process_querystring($query, $request);
                //TODO: Select attached data fields
                $entity = $query->childOf($id)->get()->compact();
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, true, 'getChildren', '{}');

                return (new ApiResponse($entity, true))->response();
            } else {
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getChildren',
                    json_encode(["error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getChildren',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     * Display entity's ancestors.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function getAncestors($id, Request $request)
    {
        try {
            if (Gate::allows(AuthServiceProvider::READ_ENTITY, [$id, 'getAncestors', "{}"])) {
                $lang = $request->input('lang', Config::get('general.langs')[0]);
                $query = Entity::select();
                $query = process_querystring($query, $request, ['treealias' => 'rel_tree_anc']);
                //TODO: Select attached data fields
                $entity = $query->ancestorOf($id)->get()->compact();
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, true, 'getAncestors', '{}');

                return (new ApiResponse($entity, true))->response();
            } else {
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getAncestors',
                    json_encode(["error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getAncestors',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     * Display entity's ancestors.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function getDescendants($id, Request $request)
    {
        try {
            if (Gate::allows(AuthServiceProvider::READ_ENTITY, [$id, 'getDescendants', "{}"])) {
                $lang = $request->input('lang', Config::get('general.langs')[0]);
                $query = Entity::select();
                $query = process_querystring($query, $request);
                //TODO: Select attached data fields
                $entity = $query->descendantOf($id)->get()->compact();
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, true, 'getDescendants', '{}');

                return (new ApiResponse($entity, true))->response();
            } else {
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getDescendants',
                    json_encode(["error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getDescendants',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     * Display entity's relations.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function getRelations($id, $kind = null, Request $request)
    {
        try {
            if (Gate::allows(AuthServiceProvider::READ_ENTITY, [$id, 'getRelations', "{}"])) {
                $lang = $request->input('lang', Config::get('general.langs')[0]);
                $query = Entity::select();
                $query = process_querystring($query, $request);
                $entities = $query->relatedBy($id, $kind)->get()->compact();
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, true, 'getRelations', '{}');

                return (new ApiResponse($entities, true))->response();
            } else {
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getRelations',
                    json_encode(["error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getRelations',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     * Display entity's inverse relations.
     *
     * @param $id String the ID of the entity
     * @param $kind String Kind of relation, for example medium, join, like...
     * @param $request \Illuminate\Http\Request
     *
     * @return \Illuminate\Http\Response
     */
    public function getInverseRelations($id, $kind = null, Request $request = null)
    {
        try {
            if (Gate::allows(AuthServiceProvider::READ_ENTITY, [$id, 'getInverseRelations', "{}"])) {
                $lang = $request->input('lang', Config::get('general.langs')[0]);
                $query = Entity::select();
                $query = process_querystring($query, $request);
                $entities = $query->relating($id, $kind)->get()->compact();
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, true, 'getInverseRelations',
                    '{}');

                return (new ApiResponse($entities, true))->response();
            } else {
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getInverseRelations',
                    json_encode(["error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], $id, AuthServiceProvider::READ_ENTITY, false, 'getInverseRelations',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     * Create the specified relation.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function postRelation($id, Request $request)
    {
        try {
            if (Gate::allows(AuthServiceProvider::WRITE_ENTITY, [$id, 'relation']) === true) {
                // TODO: Filter the json to delete al not used data
                $this->validate($request, [
                    "id"   => "required|string",
                    "kind" => "required|string",
                ], Config::get('validator.messages'));
                $body = $request->json()->all();
                $relationPosted = Entity::find($id)->addRelation($body);
                Activity::add(\Auth::user()['id'], $relationPosted['id'], AuthServiceProvider::WRITE_ENTITY, true,
                    'postRelation', json_encode(["body" => $body]));

                return (new ApiResponse($relationPosted, true))->response();
            } else {
                Activity::add(\Auth::user()['id'], '', AuthServiceProvider::WRITE_ENTITY, false, 'postRelation',
                    json_encode(["body" => $body, "error" => ApiResponse::TEXT_FORBIDDEN]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], '', AuthServiceProvider::WRITE_ENTITY, false, 'postRelation',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }

    /**
     * Deletes the specified relation.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteRelation($id, $kind, $called)
    {
        try {
            // TODO: Filter the json to delete al not used data
            if (Gate::allows(AuthServiceProvider::WRITE_ENTITY, [$id, 'relation']) === true) {
                $relationDeleted = Entity::find($id)->deleteRelation($kind, $called);
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::WRITE_ENTITY, true, 'deleteRelation',
                    json_encode(["body" => ["called" => $called, "kind" => $kind]]));

                return (new ApiResponse($relationDeleted, true))->response();
            } else {
                Activity::add(\Auth::user()['id'], $id, AuthServiceProvider::WRITE_ENTITY, false, 'deleteRelation',
                    json_encode([
                        "body"  => ["called" => $called, "kind" => $kind],
                        "error" => ApiResponse::TEXT_FORBIDDEN,
                    ]));

                return (new ApiResponse(null, false, ApiResponse::TEXT_FORBIDDEN,
                    ApiResponse::STATUS_FORBIDDEN))->response();
            }
        } catch (\Throwable $e) {
            $exceptionDetails = ExceptionDetails::filter($e);
            Activity::add(Auth::user()['id'], $id, AuthServiceProvider::WRITE_ENTITY, false, 'postRelation',
                json_encode(["error" => $exceptionDetails]));

            return (new ApiResponse(null, false, $exceptionDetails, $exceptionDetails['code']))->response();
        }
    }
}
