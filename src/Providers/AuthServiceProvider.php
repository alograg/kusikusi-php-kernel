<?php

namespace Cuatromedios\Kusikusi\Providers;

use App\Models\User;
use Cuatromedios\Kusikusi\Models\Entity;
use Cuatromedios\Kusikusi\Models\Permission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Cuatromedios\Kusikusi\Models\Authtoken;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {

        Gate::define('get-entity', function ($user, $entity_id) {
            $entity = Entity::where("id", $entity_id)->firstOrFail();
            foreach ($user->permissions as $permission) {
                $isSelfOrDescendant = Entity::isSelfOrDescendant($entity_id, $permission->entity_id);
                if ($isSelfOrDescendant && ($permission->get === Permission::ANY || ($permission->get === Permission::OWN && $entity->created_by === $user->entity_id))) {
                    return true;
                }
            }
            return false;
        });
        Gate::define('get-all', function ($user) {
            foreach ($user->permissions as $permission) {
                if ($permission->get === Permission::ANY && $permission->entity_id === 'root') {
                    return true;
                }
            }
            return false;
        });
        Gate::define('post-entity', function ($user, $entity_id) {
            $entity = Entity::where("id", $entity_id)->firstOrFail();
            foreach ($user->permissions as $permission) {
                $isSelfOrDescendant = Entity::isSelfOrDescendant($entity_id, $permission->entity_id);
                if ($isSelfOrDescendant && ($permission->post === Permission::ANY || ($permission->post === Permission::OWN && $entity->created_by === $user->entity_id))) {
                    return true;
                }
            }
            return false;
        });
        Gate::define('patch-entity', function ($user, $entity_id) {
            $entity = Entity::where("id", $entity_id)->firstOrFail();
            foreach ($user->permissions as $permission) {
                $isSelfOrDescendant = Entity::isSelfOrDescendant($entity_id, $permission->entity_id);
                if ($isSelfOrDescendant && ($permission->patch === Permission::ANY || ($permission->patch === Permission::OWN && $entity->created_by === $user->entity_id))) {
                    return true;
                }
            }
            return false;
        });
        Gate::define('delete-entity', function ($user, $entity_id) {
            $entity = Entity::where("id", $entity_id)->firstOrFail();
            foreach ($user->permissions as $permission) {
                $isSelfOrDescendant = Entity::isSelfOrDescendant($entity_id, $permission->entity_id);
                if ($isSelfOrDescendant && ($permission->delete === Permission::ANY || ($permission->delete === Permission::OWN && $entity->created_by === $user->entity_id))) {
                    return true;
                }
            }
            return false;
        });

        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.

        $this->app['auth']->viaRequest('api', function ($request) {
            if ($request->header(Authtoken::AUTHORIZATION_HEADER)) {
                $key = explode(' ',$request->header(Authtoken::AUTHORIZATION_HEADER))[1];
                // TODO: Also check the ip of the request, stored in the tokens table
                $user = User::whereHas('authtokens', function ($query) use ($key) {
                    $query->where('token', '=', $key);
                })->first();
                if(!empty($user)){
                    $request->request->add(['user_id' => $user->entity_id]);
                    $request->request->add(['user_profile' => $user->profile]);
                }
                return $user;
            } else {
                return NULL;
            }
        });
    }
}
