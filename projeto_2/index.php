<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;

// Configuração do banco de dados
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'nome_do_banco_de_dados',
    'username' => 'nome_de_usuario',
    'password' => 'senha',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Modelo de usuário
class User extends Illuminate\Database\Eloquent\Model
{
    protected $fillable = ['name', 'email', 'password'];

    public function generateToken()
    {
        $payload = [
            'iss' => 'your-iss-here',
            'sub' => $this->id,
            'iat' => time(),
            'exp' => time() + 60 * 60, // Expire in 1 hour
        ];

        return JWT::encode($payload, 'your-secret-key');
    }
}

// Rotas públicas
Route::post('/register', function (Request $request) {
    $validatedData = $request->validate([
        'name' => 'required',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6',
    ]);

    $user = User::create([
        'name' => $validatedData['name'],
        'email' => $validatedData['email'],
        'password' => Hash::make($validatedData['password']),
    ]);

    $token = $user->generateToken();

    return response()->json(['token' => $token], 201);
});

Route::post('/login', function (Request $request) {
    $credentials = $request->only('email', 'password');

    $user = User::where('email', $credentials['email'])->first();

    if (!$user || !Hash::check($credentials['password'], $user->password)) {
        return response()->json(['error' => 'Invalid credentials'], 401);
    }

    $token = $user->generateToken();

    return response()->json(['token' => $token]);
});

// Rotas protegidas
Route::group(['middleware' => 'auth'], function () {
    Route::get('/users', function () {
        $users = User::all();
        return response()->json($users);
    });

    Route::get('/profile', function (Request $request) {
        return response()->json($request->user());
    });

    Route::post('/logout', function (Request $request) {
        // Lógica de logout
        return response()->json(['message' => 'Logout successful']);
    });
});

// Middleware de autenticação
Route::middleware('auth')->get('/authenticated', function () {
    return response()->json(['message' => 'Authenticated']);
});

// Inicialização do aplicativo
$app = new Illuminate\Foundation\Application(__DIR__);
$app->instance('request', Request::capture());
$app->run();
