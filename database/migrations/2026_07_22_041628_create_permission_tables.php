<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * spatie/laravel-permission tables. Adapted from the package's published stub to
 * this project's standards: typed config access (PHPStan level 9, no baseline) and
 * the single-guard, teams-off path only — Suivre runs one guard on Postgres (D18),
 * so the stub's teams and sqlite-testing branches are dropped rather than carried.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = $this->name('permission.table_names.permissions', 'permissions');
        $roles = $this->name('permission.table_names.roles', 'roles');
        $modelHasPermissions = $this->name('permission.table_names.model_has_permissions', 'model_has_permissions');
        $modelHasRoles = $this->name('permission.table_names.model_has_roles', 'model_has_roles');
        $roleHasPermissions = $this->name('permission.table_names.role_has_permissions', 'role_has_permissions');
        $pivotRole = $this->name('permission.column_names.role_pivot_key', 'role_id');
        $pivotPermission = $this->name('permission.column_names.permission_pivot_key', 'permission_id');
        $morphKey = $this->name('permission.column_names.model_morph_key', 'model_id');

        Schema::create($permissions, static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($roles, static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($modelHasPermissions, static function (Blueprint $table) use ($permissions, $pivotPermission, $morphKey): void {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->unsignedBigInteger($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($permissions)
                ->cascadeOnDelete();

            $table->primary(
                [$pivotPermission, $morphKey, 'model_type'],
                'model_has_permissions_permission_model_type_primary',
            );
        });

        Schema::create($modelHasRoles, static function (Blueprint $table) use ($roles, $pivotRole, $morphKey): void {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->unsignedBigInteger($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id')
                ->on($roles)
                ->cascadeOnDelete();

            $table->primary(
                [$pivotRole, $morphKey, 'model_type'],
                'model_has_roles_role_model_type_primary',
            );
        });

        Schema::create($roleHasPermissions, static function (Blueprint $table) use ($permissions, $roles, $pivotRole, $pivotPermission): void {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($permissions)
                ->cascadeOnDelete();

            $table->foreign($pivotRole)
                ->references('id')
                ->on($roles)
                ->cascadeOnDelete();

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists($this->name('permission.table_names.role_has_permissions', 'role_has_permissions'));
        Schema::dropIfExists($this->name('permission.table_names.model_has_roles', 'model_has_roles'));
        Schema::dropIfExists($this->name('permission.table_names.model_has_permissions', 'model_has_permissions'));
        Schema::dropIfExists($this->name('permission.table_names.roles', 'roles'));
        Schema::dropIfExists($this->name('permission.table_names.permissions', 'permissions'));
    }

    /**
     * A configured table/column name, falling back to spatie's default when the
     * config leaves it null (the pivot keys ship unset). Typed so the schema
     * builder receives a string under PHPStan level 9.
     */
    private function name(string $key, string $default): string
    {
        $value = config($key);

        return is_string($value) ? $value : $default;
    }
};
