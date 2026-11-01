<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cada bloque se omite si la tabla ya existe, para convivir con el esquema creado a mano en MySQL.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->increments('id_rol');
                $table->string('nombre_rol', 50)->unique();
                $table->string('descripcion', 255)->nullable();
            });
        }

        if (! Schema::hasTable('usuarios')) {
            Schema::create('usuarios', function (Blueprint $table) {
                $table->increments('id_usuario');
                $table->string('matricula', 20)->unique();
                $table->string('nombre', 60);
                $table->string('apellidos', 80);
                $table->string('correo_institucional', 120)->unique();
                $table->string('password_hash', 255)->nullable();
                $table->unsignedInteger('id_rol');
                $table->enum('estatus', ['activo', 'inactivo'])->default('activo');
                $table->string('google_id')->nullable();
                $table->timestamps();

                $table->foreign('id_rol')->references('id_rol')->on('roles');
            });
        } elseif (! Schema::hasColumn('usuarios', 'google_id')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->string('google_id')->nullable();
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
    }
};
