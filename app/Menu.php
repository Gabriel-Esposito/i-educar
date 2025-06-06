<?php

namespace App;

use App\Models\LegacyUserType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as LaravelCollection;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * @property int               id
 * @property int               parent_id
 * @property string            title
 * @property string            description
 * @property string            link
 * @property string            icon
 * @property int               order
 * @property int               type
 * @property int               process
 * @property bool              active
 * @property Menu              $parent
 * @property Collection|Menu[] $children
 */
class Menu extends Model
{
    use HasRecursiveRelationships;

    /**
     * @var array
     */
    protected $fillable = [
        'parent_id',
        'title',
        'description',
        'link',
        'icon',
        'order',
        'type',
        'parent_old',
        'old',
        'process',
        'active',
    ];

    private static function getMenusByUserType(User $user): Collection
    {
        $excludes = [
            Process::CONFIG,
            Process::SETTINGS,
        ];

        return self::withRecursiveQueryConstraint(static function (Builder $query) use ($user, $excludes) {
            $query->whereNull('menus.process');
            $query->orWhere(function ($query) use ($user, $excludes) {
                $query->whereNotNull('menus.process');
                $query->whereNotIn('menus.process', $excludes);
                $query->whereHas('userTypes', function (Builder $query) use ($user) {
                    $query->where('ref_cod_tipo_usuario', $user->ref_cod_tipo_usuario);
                });
            });
        }, static function () {
            return self::tree()->orderBy('order')->get();
        })->toTree();
    }

    public static function getMenuAncestors(Menu $menu)
    {
        return Menu::find($menu->getKey())
            ->ancestors()
            ->get()
            ->pluck('id')
            ->toArray();
    }

    private static function getMenusByIds($ids): Collection
    {
        return static::query()
            ->with([
                'children' => function ($query) use ($ids) {
                    /** @var Builder $query */
                    $query->whereNull('process');
                    $query->orWhereIn('id', $ids);
                    $query->orderBy('order');
                    $query->with([
                        'children' => function ($query) use ($ids) {
                            /** @var Builder $query */
                            $query->whereNull('process');
                            $query->orWhereIn('id', $ids);
                            $query->orderBy('order');
                            $query->with([
                                'children' => function ($query) use ($ids) {
                                    /** @var Builder $query */
                                    $query->whereNull('process');
                                    $query->orWhereIn('id', $ids);
                                    $query->orderBy('order');
                                    $query->with([
                                        'children' => function ($query) use ($ids) {
                                            /** @var Builder $query */
                                            $query->whereNull('process');
                                            $query->orWhereIn('id', $ids);
                                            $query->orderBy('order');
                                            $query->with([
                                                'children' => function ($query) use ($ids) {
                                                    /** @var Builder $query */
                                                    $query->whereNull('process');
                                                    $query->orWhereIn('id', $ids);
                                                    $query->orderBy('order');
                                                },
                                            ]);
                                        },
                                    ]);
                                },
                            ]);
                        },
                    ]);
                },
            ])
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();
    }

    /**
     * Indica se o menu é um link ou tem ao menos um link em seus submenus.
     *
     * @return bool
     */
    public function hasLink()
    {
        return $this->isLink() || $this->hasLinkInSubmenu();
    }

    /**
     * Indica se o menu é um link.
     *
     * @return bool
     */
    public function isLink()
    {
        return boolval($this->link);
    }

    /**
     * Indica se o menu tem links em seus submenus.
     *
     * @return bool
     */
    public function hasLinkInSubmenu()
    {
        foreach ($this->children as $menu) {
            if ($menu->isLink()) {
                return true;
            }

            if ($menu->hasLinkInSubmenu()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return HasMany
     */
    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id');
    }

    /**
     * @return BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    /**
     * Retorna o menu raiz.
     *
     * @return Menu
     */
    public function root()
    {
        $root = $this;

        while ($root->parent) {
            $root = $root->parent;
        }

        return $root;
    }

    /**
     * @param string            $path
     * @param LaravelCollection $process
     * @return mixed
     */
    public function processes($path, $process, $userLevel)
    {
        $collect = $this->children->reduce(
            function (LaravelCollection $collect, Menu $menu) use ($path, $process, $userLevel) {
                return $collect->merge($menu->processes($path . ' > ' . $menu->title, $process, $userLevel));
            },
            new LaravelCollection
        );

        $this->description = $path;

        if ($this->process && $this->parent_id) {
            $excludes = [
                Process::CONFIG,
                Process::SETTINGS,
            ];

            if ($userLevel !== LegacyUserType::LEVEL_ADMIN && in_array($this->process, $excludes, true)) {
                return $collect;
            }

            $collect->push(new LaravelCollection([
                'title' => $this->title,
                'description' => $this->description,
                'link' => $this->link,
                'process' => $this->id,
                'allow' => $process->get($this->id, 0),
            ]));
        }

        return $collect;
    }

    /**
     * Altera o título dos menus de "Declaração" para "Atestado".
     *
     * @return void
     */
    public static function changeMenusToAttestation()
    {
        $exception = [999229, 999812];

        static::query()
            ->where('title', 'ilike', '%Declarações%')
            ->get()
            ->each(function (Menu $menu) {
                $menu->title = str_replace('Declarações', 'Atestados', $menu->title);
                $menu->description = str_replace('Declarações', 'Atestados', $menu->description);
                $menu->save();
            });

        static::query()
            ->where('title', 'ilike', '%Declaração%')
            ->whereNotIn('old', $exception)
            ->get()
            ->each(function (Menu $menu) {
                $menu->title = str_replace('Declaração', 'Atestado', $menu->title);
                $menu->description = str_replace('Declaração', 'Atestado', $menu->description);
                $menu->save();
            });
    }

    /**
     * Altera o título dos menus de "Atestado" para "Declaração".
     *
     * @return void
     */
    public static function changeMenusToDeclaration()
    {
        $exception = [999229, 999812];

        static::query()
            ->where('title', 'ilike', '%Atestados%')
            ->whereNotIn('old', $exception)
            ->get()
            ->each(function (Menu $menu) {
                $menu->title = str_replace('Atestados', 'Declarações', $menu->title);
                $menu->description = str_replace('Atestados', 'Declarações', $menu->description);
                $menu->save();
            });

        static::query()
            ->where('title', 'ilike', '%Atestado%')
            ->whereNotIn('old', $exception)
            ->get()
            ->each(function (Menu $menu) {
                $menu->title = str_replace('Atestado', 'Declaração', $menu->title);
                $menu->description = str_replace('Atestado', 'Declaração', $menu->description);
                $menu->save();
            });
    }

    /**
     * Tipos de Usuário
     */
    public function userTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            LegacyUserType::class,
            'pmieducar.menu_tipo_usuario',
            'menu_id',
            'ref_cod_tipo_usuario',
            'id',
            'cod_tipo_usuario'
        )
            ->wherePivot('visualiza', 1);
    }

    /**
     * Retorna os menus disponíveis para um determinado usuário.
     *
     *
     * @return Collection
     */
    public static function user(User $user)
    {
        if ($user->isAdmin()) {
            return static::roots();
        }

        return static::getMenusByUserType($user);
    }

    /**
     * Retorna todos os menus disponíveis.
     *
     * @return Collection
     */
    public static function roots()
    {
        return self::tree()->orderBy('order')->get()->toTree();
    }

    /**
     * Retorna os menus disponíveis para o usuário baseado em seu nível de
     * permissão.
     *
     * @param string $search
     * @return LaravelCollection
     */
    public static function findByUser(User $user, $search)
    {
        $query = $user->isAdmin() ? static::query() : $user->menu();

        return $query->whereNotNull('link')
            ->where(function ($query) use ($search) {
                $query->orWhere('title', 'ilike', "%{$search}%");
                $query->orWhere('description', 'ilike', "%{$search}%");
            })
            ->orderBy('title')
            ->limit(15)
            ->get();
    }
}
