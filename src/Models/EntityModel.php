<?php
namespace Cuatromedios\Kusikusi\Models;

use App\Models\Entity;
use Carbon\Carbon;
use Cuatromedios\Kusikusi\Models\Http\ApiResponse;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Class EntityModel
 *
 * @package Cuatromedios\Kusikusi\Models
 */
class EntityModel extends KusikusiModel
{

    /**
     * Indicates if the IDs are auto-incrementing and is not numeric.
     *
     * @var bool
     */
    public $incrementing = false;
    /**
     * @var string
     */
    public $keyType = "string";
    /**
     * Indicates  the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;
    /**
     * @var array
     */
    protected $_contents = [];
    /**
     * @var
     */
    protected $_lang;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entities';
    /**
     * The primary key
     */
    protected $primaryKey = 'id';
    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'created_at',
        'updated_at',
        'deleted_at',
        'created_by',
        'updated_by',
        'publicated_at',
        'unpublicated_at',
        'entity_version',
        'tree_version',
        'relations_version',
        'full_version',
    ];

    /**
     * @var array
     */
    protected $hidden = ['caller_id', 'called_id'];

    /**
     * Active attribute should be casted to boolean
     *
     * @var array
     */
    protected $casts = [
        'active' => 'boolean',
        'tags'   => 'array',
    ];

    /**
     * The model should use soft deletes.
     *
     * @var array
     */
    use SoftDeletes;

    /**
     * EntityModel constructor.
     *
     * @param array $newAttributes
     * @param null $lang
     */
    public function __construct(array $newAttributes = [], $lang = null)
    {
        if ($lang == null) {
            $this->setLang(Config::get('cms.langs')[0]);
        } else {
            $this->setLang($lang);
        }
        parent::__construct($newAttributes);
    }

    /**
     * @return \Closure
     */
    public static function onlyTags()
    {
        $tags = join(" ", params_as_array(func_get_args(), 0));

        return function ($query) use ($tags) {
            $query->whereRaw("MATCH (tags) AGAINST (? IN BOOLEAN MODE)", $tags);
        };
    }

    /**
     * Events.
     *
     * @var bool
     */
    public static function boot($preset = [])
    {
        parent::boot();
        self::creating(function ($entity) {
            if (!isset($entity['model'])) {
                throw new \Exception('A model id is requiered to create a new entity', ApiResponse::STATUS_BADREQUEST);
            }
            if (isset($entity['contents'])) {
                $entity->addContents($entity['contents']);
                unset($entity['contents']);
            }
            // Set data in the correspondent table
            if (isset($entity['data'])) {
                $entity->addData($entity['data'], $entity['model']);
                unset($entity['data']);
            } elseif (isset($entity[$entity['model']]) && is_array($entity[$entity['model']])) {
                $entity->addData($entity[$entity['model']], $entity['model']);
                unset($entity[$entity['model']]);
            }
        });
        self::created(function ($entity) {
            // Create the ancestors relations
            if (isset($entity['parent_id']) && $entity['parent_id'] != null) {
                $parentEntity = Entity::find($entity['parent_id']);
                $entity->addRelation(['id' => $parentEntity['id'], 'kind' => 'ancestor', 'depth' => 1]);
                $ancestors = ($parentEntity->relations()->where('kind', 'ancestor')->orderBy('depth'))->get();
                for ($a = 0; $a < count($ancestors); $a++) {
                    $entity->addRelation(['id' => $ancestors[$a]['id'], 'kind' => 'ancestor', 'depth' => ($a + 2)]);
                }
            };
        });
        self::saved(function ($entity) {
            self::updateEntityVersion($entity->id);
        });
        self::updating(function ($entity) {
            if (isset($entity['contents'])) {
                $entity->addContents($entity['contents']);
                unset($entity['contents']);
            }
            // Set data in the correspondent table
            if (isset($entity['data'])) {
                $entity->addData($entity['data'], $entity['model']);
                unset($entity['data']);
            } elseif (isset($entity[$entity['model']]) && is_array($entity[$entity['model']])) {
                $entity->addData($entity[$entity['model']], $entity['model']);
                unset($entity[$entity['model']]);
            }
            $oldEntity = Entity::find($entity->id);
            if ($oldEntity->parent_id !== $entity->parent_id) {
                if (self::isSelfOrDescendant($entity->parent_id, $entity->id)) {
                    throw new \Exception('Can not change the parent of an entity to a descendant of itself', 401);
                }
                $oldEntity->recreateTree($entity->parent_id);
            }
        });
        self::updated(function ($entity) {
        });
    }

    /**
     * Get true if an entity is descendant of another or itself.
     *
     * @param string $isEntity_id The id of the reference entity
     * @param string $descendantOf_id The id of the entity to know is an ancestor of
     *
     * @return Boolean
     */
    public static function isSelfOrDescendant($isEntity_id, $descendantOf_id)
    {
        if ($isEntity_id === $descendantOf_id) {
            return true;
        } else {
            return Entity::isDescendant($isEntity_id, $descendantOf_id);
        }
    }

    /**
     * Get true if an entity is descendant of another.
     *
     * @param string $isEntity_id The id of the reference entity
     * @param string $descendantOf_id The id of the entity to know is an ancestor of
     *
     * @return Boolean
     */
    public static function isDescendant($isEntity_id, $descendantOf_id)
    {
        $ancestors = Entity::select('entities.id')->ancestorOf($isEntity_id)->get();
        foreach ($ancestors as $ancestor) {
            if ($ancestor->id === $descendantOf_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deletes an entity and its relations
     *
     * @param boolean $hard Allows to completely delete an entity and its relation from the database
     */
    public function deleteEntity($hard = false)
    {
        if (!$hard) {
            self::updateEntityVersion($this->id);
            Entity::destroy($this->id);
        } else {
            $modelClass = Entity::getClassFromModelId($this->model);
            if (file_exists($modelClass) && count($modelClass::$dataFields) > 0) {
                $modelClass::destroy($this->id);
            }
            self::updateEntityVersion($this->id);
            $this->relations()->detach();
            $this->forceDelete();
        }
    }

    /**
     * Updates the entity version, tree version and full version of the given entity
     * as well as it´s ancestors (and inverse relations)
     *
     * @param $id
     */
    private static function updateEntityVersion($entity_id)
    {
        // Updates the version of the own entity and its full version as well
        DB::table('entities')
          ->where('id', $entity_id)
          ->increment('entity_version')
        ;
        DB::table('entities')
          ->where('id', $entity_id)
          ->increment('full_version')
        ;
        // Then the three version (and full version), using its ancestors
        $ancestors = Entity::select()->ancestorOf($entity_id)->get();
        if (!empty($ancestors)) {
            foreach ($ancestors as $ancestor) {
                DB::table('entities')
                  ->where('id', $ancestor['id'])
                  ->increment('tree_version')
                ;
                DB::table('entities')
                  ->where('id', $ancestor['id'])
                  ->increment('full_version')
                ;
            }
        }
        // Now updates the tree and full version of the relations entity's ancestors and the relation version of the given entity
        $relateds = Entity::where('id', $entity_id)->withInverseRelations()->get()->compact();
        if (count($relateds) > 0 && count($relateds[0]['inverse_relations']) > 0) {
            foreach ($relateds[0]['inverse_relations'] as $related) {
                $ancestors = Entity::select()->ancestorOf($related['id'])->get();
                if (!empty($ancestors)) {
                    foreach ($ancestors as $ancestor) {
                        DB::table('entities')
                          ->where('id', $ancestor['id'])
                          ->increment('tree_version')
                        ;
                        DB::table('entities')
                          ->where('id', $ancestor['id'])
                          ->increment('full_version')
                        ;
                    }
                }
                DB::table('entities')
                  ->where('id', $related['id'])
                  ->increment('relations_version')
                ;
            }
        }
    }

    /**
     *  Return a class from a string
     */
    private static function getClassFromModelId($modelId)
    {
        if (isset($modelId) && $modelId != '') {
            return ("\\App\\Models\\" . (studly_case($modelId)));
        } else {
            return null;
        }
    }

    /**
     * The relations that belong to the entity.
     */
    public function relations()
    {
        return $this
            ->belongsToMany('App\Models\Entity', 'relations', 'caller_id', 'called_id')
            ->using('Cuatromedios\Kusikusi\Models\Relation')
            ->as('relation')
            ->withPivot('kind', 'position', 'depth', 'tags')
            ->withTimestamps()
            ;
    }

    /**
     * Adds content rows to an Entity.
     *
     * @param  array $contents An arrray of one or more contents in field => value format for example ["title" => "The Title", "summary", "The Summary"]
     * @param  string $lang optional language code, for example "en" or "es-mx"
     */
    public function addContents($contents, $lang = null)
    {
        $lang = $lang ?? $this->_lang ?? Config::get('cms.langs')[0] ?? '';
        foreach ($contents as $key => $value) {
            EntityContent::updateOrCreate(
                [
                    "id" => "{$this->id}_{$lang}_{$key}",
                ], [
                "entity_id" => $this->id,
                "field"     => $key,
                "value"     => $value,
                "lang"      => $lang,
            ]);
        }
    }

    /**
     * Deletes content rows to an Entity.
     *
     * @param  array $fields An arrray of one or more field names
     * @param  string $lang optional language code, for example "en" or "es-mx"
     */
    public function deleteContents($fields, $lang = null)
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }
        $lang = $lang ?? $this->_lang ?? Config::get('cms.langs')[0] ?? '';
        $idstodelete = [];
        foreach ($fields as $field) {
            $idstodelete[] = "{$this->id}_{$lang}_{$field}";
        }
        EntityContent::destroy($idstodelete);
    }


    /**************************
     *
     * ACCESORS
     *
     *************************/

    public function compact()
    {
        return EntityModel::compactContents($this);
    }


    /**************************
     *
     * SCOPES
     *
     **************************/

    public static function compactContents($array)
    {
        //TODO: This may be very inneficient :S
        if (!is_array($array)) {
            $array = $array->toArray();
        }
        foreach ($array as $key => $value) {
            if ($key === "contents") {
                $compactedContents = [];
                foreach ($value as $content) {
                    $compactedContents[$content['field']] = $content['value'];
                };
                $array[$key] = $compactedContents;
            } else {
                if ($key === 'model' && $value === 'medium' && isset($array['contents']) && isset($array['medium'])) {
                    $result = array_search('title', array_column($array['contents'], 'field'));
                    if ($result !== false) {
                        $slug = str_slug($array['contents'][0]['value']);
                    } else {
                        $slug = "media";
                    }
                    foreach (Config::get('media.presets') as $presetKey => $presetValue) {
                        $array['medium'][$presetKey] = "/media/{$array['medium']['id']}/{$presetKey}/{$slug}.{$presetValue['format']}";
                    }
                } else {
                    if ($key === 'medium') {
                    } else {
                        if (is_array($value)) {
                            $array[$key] = EntityModel::compactContents($value);
                        }
                    }
                }
            }
        }

        return $array;
    }

    /**
     * @return mixed
     */
    public function getLang()
    {
        return $this->_lang;
    }

    /**
     * @param $lang
     */
    public function setLang($lang)
    {
        $this->_lang = $lang;
    }

    /**
     * Adds data rows to a separate table related to the entity.
     *
     * @param  array $data An arrray of one or more contents in field => value format for example ["format" => "png", "size", "1080"]
     * @param  string $model required to identify in which table the data wil be saved
     */
    public function addData($data, $model)
    {
        $modelClass = Entity::getClassFromModelId($model);
        $modelClass::updateOrCreate(
            [
                "id" => $this->id,
            ], $data);
    }

    /**
     * @param null $new_parent_id
     * @param bool $withDescendants
     */
    public function recreateTree($new_parent_id = null, $withDescendants = true)
    {
        // TODO: Use Queues to process all descendants need to be updated, this may take too much time.
        ini_set('max_execution_time', 300);
        $relationsToCreate = [];
        if (null == $new_parent_id) {
            $new_parent_id = $this->parent_id;
        }
        $newParentEntity = Entity::withTrashed()->find($new_parent_id);
        $relationsToCreate[] = $this->updatedRelationObject($newParentEntity['id'], 1);
        $ancestors = ($newParentEntity->relations()->where('kind', 'ancestor')->orderBy('depth'))->get();
        for ($a = 0; $a < count($ancestors); $a++) {
            $relationsToCreate[] = $this->updatedRelationObject($ancestors[$a]['id'], $a + 2);
        }
        Relation::where('caller_id', $this->id)
                ->where('kind', 'ancestor')
                ->delete()
        ;
        for ($r = 0; $r < count($relationsToCreate); $r++) {
            $this->addRelation($relationsToCreate[$r]['relation']);
        }
        if ($withDescendants) {
            $descendants = Entity::select('id', 'parent_id')->descendantOf($this->id, 'asc')->get();
            foreach ($descendants as $descendant) {
                $descendant->recreateTree($descendant->parent_id, false);
            }
        }
    }

    /**
     * @param $ancestor_id
     * @param $depth
     *
     * @return array
     */
    private function updatedRelationObject($ancestor_id, $depth)
    {
        $previousRelation = Relation::where("caller_id", $this->id);
        if ($depth != 1) {
            $previousRelation->where('called_id', $ancestor_id);
        } else {
            $previousRelation->where('depth', 1);
        }
        $previousRelation->where('kind', 'ancestor');
        $previousRelation = $previousRelation->first();
        $tags = [];
        $position = 0;
        if ($previousRelation) {
            $position = $previousRelation->position;
            $tags = $previousRelation->tags;
        }

        return [
            'id'       => $this->id,
            'relation' => [
                'id'       => $ancestor_id,
                'kind'     => 'ancestor',
                'position' => $position,
                'depth'    => $depth,
                'tags'     => $tags,
            ],
        ];
    }

    /**
     * Dinamically creates relations
     */
    public function addRelation($data)
    {
        if (isset($data['id'])) {
            $id = $data['id'];
            unset($data['id']);
            // TODO: Maybe filter through illuminate ValidationException
            if (!isset($data['kind'])) {
                $data['kind'] = 'relation';
            }
            if (!isset($data['position'])) {
                $data['position'] = 0;
            }
            if (!isset($data['tags'])) {
                $data['tags'] = [];
            }
            if (is_string($data['tags'])) {
                $data['tags'] = explode(',', $data['tags']);
            }
            if (!isset($data['depth'])) {
                $data['depth'] = 0;
            }
            if (count($this->relations()->where(['called_id' => $id, 'kind' => $data['kind']])->get()) > 0) {
                $this->relations()->updateExistingPivot($id, $data);
            } else {
                $this->relations()->attach($id, $data);
            }
            self::updateRelationVersion($this->id);

            return ['id' => $id];
        }
    }

    /**
     * Updates the relation version of the caller entity and updates
     * the tree version and full version of the called entity and it's
     * ancestors
     *
     * @param $caller
     * @param $called
     */
    private static function updateRelationVersion($caller_id)
    {
        // Updates the version of the own entity and its full version as well
        DB::table('entities')
          ->where('id', $caller_id)
          ->increment('relations_version')
        ;
        DB::table('entities')
          ->where('id', $caller_id)
          ->increment('full_version')
        ;
        $ancestors = Entity::select()->ancestorOf($caller_id)->get();
        if (!empty($ancestors)) {
            foreach ($ancestors as $ancestor) {
                DB::table('entities')
                  ->where('id', $ancestor['id'])
                  ->increment('full_version')
                ;
            }
        }
    }

    /**
     * Creates a virtual field where it determinates whether the entity is published or not
     *
     * @return boolean
     */
    public function getPublishedAttribute()
    {
        $currentDate = Carbon::now();
        if ($this->active === true && $this->publicated_at <= $currentDate && $this->unpublicated_at > $currentDate && $this->deleted_at === null) {
            return true;
        }

        return false;
    }



    /**
     * Scope a query to only include relations where the given id is called.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $entity_id The id of the parent entity
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    // public function scopeInverseRelationsOf($query, $entity_id, $order = 'desc')
    // {
    //   if ($order != 'asc') {
    //     $order = 'desc';
    //   }
    //   $query->join('relations as rel_invR', function ($join) use ($entity_id) {
    //     $join->on('rel_invR.caller_id', '=', 'id')
    //         ->where('rel_invR.called_id', '=', $entity_id)
    //         ;
    //   })->orderBy('rel_invR.depth', $order);
    // }

    /**
     * Scope a query to only include entities of a given modelId.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  mixed $modelId
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfModel($query, $modelId)
    {
        return $query->where('model', $modelId);
    }

    /**
     * Scope a query include entity's data fields.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $tags The id of the Entity
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    // public function scopeWithData($query)
    // {
    //   $modelClass = Entity::getClassFromModelId($this->model);
    //   if ($modelClass !== NULL && count($modelClass::$dataFields) > 0) {
    //     $data_fields = params_as_array(func_get_args(), 1);
    //     if (count($data_fields) == 0) {
    //       $query->with($this->model);
    //     } else {
    //       $query->with([$this->model => function($query) use ($data_fields) {
    //         foreach ($data_fields as $field) {
    //           $query->addSelect($field);
    //         }
    //       }]);
    //     }
    //   }
    //   return $query;
    // }
    /**
     * Scope a query include relations of type medium.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $tags The id of the Entity
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    /*public function scopeWithMedia($query, $tags = NULL)
    {
      $tags = params_as_array(func_get_args(), 1);
      $query->withRelations(function($query) use ($tags) {
        $query->select('*')
            ->whereModel('medium')
            ->whereKind('medium');
        if (count($tags) > 0) {
          $query->whereTags($tags);
        }
        $query->with(['medium' => function($query) {
        }])
            ->orderBy('position', 'asc')
            ->withContents('title');
      });
      return $query;
    }*/

    /**
     * Scope a query to only include published entities.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIsPublished($query)
    {
        return $query->where('active', true)
                     ->whereDate('publicated_at', '<=', Carbon::now())
                     ->whereDate('unpublicated_at', '>', Carbon::now())
                     ->where('deleted_at')
            ;
    }

    /**
     * Scope a query to only include children of a given parent id.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $entity_id The id of the parent entity
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeChildOf($query, $parent_id)
    {
        $query->join('relations as rel_child', function ($join) use ($parent_id) {
            $join->on('rel_child.caller_id', '=', 'entities.id')
                 ->where('rel_child.called_id', '=', $parent_id)
                 ->where('rel_child.depth', '=', 1)
                 ->where('rel_child.kind', '=', 'ancestor')
            ;
        });
    }

    /**
     * Scope a query to only include the parent of the given id.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $entity_id The id of the parent entity
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeParentOf($query, $id)
    {
        $query->join('relations as rel_par', function ($join) use ($id) {
            $join->on('rel_par.called_id', '=', 'entities.id')
                 ->where('rel_par.caller_id', '=', $id)
                 ->where('rel_par.depth', '=', 1)
                 ->where('rel_par.kind', '=', 'ancestor')
            ;
        });
    }

    /**
     * Scope a query to only include ancestors of a given entity.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $entity_id The id of the parent entity
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAncestorOf($query, $entity_id, $order = 'desc')
    {
        if ($order != 'asc') {
            $order = 'desc';
        }
        $query->join('relations as rel_tree_anc', function ($join) use ($entity_id) {
            $join->on('rel_tree_anc.called_id', '=', 'entities.id')
                 ->where('rel_tree_anc.caller_id', '=', $entity_id)
                 ->where('rel_tree_anc.kind', '=', 'ancestor')
            ;
        })->orderBy('rel_tree_anc.depth', $order)
        ;
    }

    /**
     * Scope a query to only include children of a given parent id.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $entity_id The id of the parent entity
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDescendantOf($query, $entity_id, $order = 'desc', $depth = null)
    {
        if ($order != 'asc') {
            $order = 'desc';
        }
        if ($depth == null) {
            $depth = 9999;
        }
        $query->join('relations as rel_tree_des', function ($join) use ($entity_id, $depth) {
            $join->on('rel_tree_des.caller_id', '=', 'entities.id')
                 ->where('rel_tree_des.called_id', '=', $entity_id)
                 ->where('rel_tree_des.kind', '=', 'ancestor')
                 ->where('rel_tree_des.depth', '<=', $depth)
            ;
        })->orderBy('rel_tree_des.depth', $order)
        ;
    }

    /**
     * Scope a query to only get entities being called by.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $entity_id The id of the entity calling the relations
     * @param  string $kind Filter by type of relation, if ommited all relations but 'ancestor' will be included
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRelatedBy($query, $entity_id, $kind = null)
    {
        $query->join('relations as rel_by', function ($join) use ($entity_id, $kind) {
            $join->on('rel_by.called_id', '=', 'entities.id')
                 ->where('rel_by.caller_id', '=', $entity_id)
            ;
            if ($kind === null) {
                $join->where('rel_by.kind', '!=', 'ancestor');
            } else {
                $join->where('rel_by.kind', '=', $kind);
            }
        })->addSelect('rel_by.kind', 'rel_by.position', 'rel_by.depth', 'rel_by.tags')
        ;
    }

    /**
     * Scope a query to only get entities calling.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $entity_id The id of the entity calling the relations
     * @param  string $kind Filter by type of relation, if ommited all relations but 'ancestor' will be included
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRelating($query, $entity_id, $kind = null)
    {
        $query->join('relations as i_rel_by', function ($join) use ($entity_id, $kind) {
            $join->on('i_rel_by.caller_id', '=', 'entities.id')
                 ->where('i_rel_by.called_id', '=', $entity_id)
            ;
            if ($kind === null) {
                $join->where('i_rel_by.kind', '!=', 'ancestor');
            } else {
                $join->where('i_rel_by.kind', '=', $kind);
            }
        })->addSelect('i_rel_by.kind', 'i_rel_by.position', 'i_rel_by.depth', 'i_rel_by.tags')
        ;
    }

    /**
     * Scope a query to only get entities being called by another of type medium.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $entity_id The id of the entity calling the relations
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMediaOf($query, $entity_id)
    {
        $query->join('relations as rel_media', function ($join) use ($entity_id) {
            $join->on('rel_media.called_id', '=', 'entities.id')
                 ->where('rel_media.caller_id', '=', $entity_id)
                 ->where('rel_media.kind', '=', 'medium')
            ;
        })
              ->addSelect('id', 'model', 'rel_media.kind', 'rel_media.position', 'rel_media.depth', 'rel_media.tags')
              ->orderBy('position')
              ->withContents('title')
              ->with('medium')
        ;
    }

    /**************************
     *
     * RELATIONS
     *
     **************************/

    /**
     * Scope a query to include the contents.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $content_fields The id of the Entity
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithContents($query)
    {
        $content_fields = params_as_array(func_get_args(), 1);
        if (count($content_fields) == 0) {
            $query->with('contents');
        } else {
            $query->with([
                'contents' => function ($query) use ($content_fields) {
                    $first = true;
                    foreach ($content_fields as $field) {
                        if ($first) {
                            $query->where('field', '=', $field);
                            $first = false;
                        } else {
                            $query->orWhere('field', '=', $field);
                        }
                    }
                },
            ]);
        }

        return $query;
    }

    /**
     * Get the other models related.
     */
    // public function data()
    // {
    //   $modelClass = Entity::getDataClass($this->model);
    //   if ($modelClass && count($modelClass::$dataFields) > 0) {
    //     return $this->hasOne($modelClass, 'id');
    //   } else {
    //     return $this->hasOne('App\Models\Entity', 'id');
    //   }
    // }

    /**
     * Scope a query to order by a content field.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $content_fields The id of the Entity
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByContents($query, $field, $order = "asc")
    {
        if ($order != 'desc') {
            $order = 'asc';
        }
        $query->leftJoin('contents as content_for_order', function ($join) use ($field) {
            $join->on('content_for_order.entity_id', '=', 'entities.id')
                 ->where('field', '=', $field)
            ;
        });
        $query->orderBy("content_for_order.value", $order);
    }

    /**
     * Scope a query to order by a data field.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $content_fields The id of the Entity
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByData($query, $model, $field, $order = "asc")
    {
        if ($order != 'desc') {
            $order = 'asc';
        }
        $table = str_plural($model);
        $query->leftJoin($table, function ($join) use ($table, $field) {
            $join->on("{$table}.id", '=', 'entities.id');
        });
        $query->orderBy("{$table}.{$field}", $order);
    }

    /**
     * Scope a query to only include relations having specific tags.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereTags($query)
    {
        $tags = join(" ", params_as_array(func_get_args(), 1));
        $query->whereRaw("MATCH (tags) AGAINST (? IN BOOLEAN MODE)", $tags);

        return $query;
    }

    /**
     * Appends relations of an Entity.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     *
     * @return function Function that receives and returns a query
     */
    public function scopeWithRelations($query, $function = null)
    {
        if (null == $function) {
            return $query->with("relations");
        }

        return $query->with(["relations" => $function]);
    }

    /**
     * Appends inverse relations of an Entity.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     *
     * @return function Function that receives and returns a query
     */
    public function scopeWithInverseRelations($query, $function = null)
    {
        if (null == $function) {
            return $query->with("inverseRelations");
        }

        return $query->with(["inverseRelations" => $function]);
    }

    /**
     * The relation data between two entities
     */
    // public function relationData()
    // {
    //   return $this
    //       ->hasMany('Cuatromedios\Kusikusi\Models\Relation', 'called_id');
    // }

    /**
     * Appends the parent of an Entity.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     *
     * @return function Function that receives and returns a query
     */
    public function scopeWithParent($query, $function = null)
    {
        $query->addSelect('parent_id');
        if (null == $function) {
            return $query->with('parent');
        }

        return $query->with(["parent" => $function])
                     ->WithRelationData(function ($filter) {
                         $filter->where('called_id', $this->parent_id);
                     })
            ;
    }

    /**
     * Appends relation data of an Entity
     */
    public function scopeWithRelationData($query, $function = null)
    {
        if (null == $function) {
            return $query->with("relationData");
        }

        return $query->with(["relationData" => $function]);
    }

    /**
     * Set the contents relation of the EntityBase.
     */
    public function contents()
    {
        return $this->hasMany('Cuatromedios\Kusikusi\Models\EntityContent', 'entity_id');
    }

    /**
     * The relations that belong to the entity.
     */
    public function media()
    {
        return $this->relations()
                    ->select('id', 'model')
                    ->where('kind', '=', 'medium')
                    ->orderBy('position', 'asc')
                    ->withContents('title')
                    ->with('medium')
            ;
    }

    /**
     * The inverse relations that belong to the entity.
     */
    public function inverseRelations()
    {
        return $this
            ->belongsToMany('App\Models\Entity', 'relations', 'called_id', 'caller_id')
            ->using('Cuatromedios\Kusikusi\Models\Relation')
            ->as('inverseRelations')
            ->where('kind', '!=', 'ancestor')
            ->withPivot('kind', 'position', 'depth', 'tags')
            ->withTimestamps()
            ;
    }

    /**
     * Get the activity related to the Entity.
     */
    public function activity()
    {
        return $this->hasMany('Cuatromedios\\Kusikusi\\Models\\Activity', 'entity_id');
    }

    /**
     * The parent of the entity.
     */
    public function parent()
    {
        return $this
            ->belongsTo('App\Models\Entity', 'parent_id');
    }

    /**
     * Deletes an specified relation
     */
    public function deleteRelation($kind, $called_id)
    {
        $where = ['caller_id' => $this->id, 'called_id' => $called_id, 'kind' => $kind];
        self::updateRelationVersion($this->id);
        Relation::where($where)->delete();
    }

    //TODO: Override the select method of the model, to change all fields set to full entities.field format and avoid ambiguous id fields when joining other tables
}
