<?php

namespace App\Http\Controllers\Auth;

use App\Dominio\Economia\Actions\RegistrarTransaccionAction;
use App\Dominio\Economia\Enums\Moneda;
use App\Dominio\Economia\Enums\MotivoTransaccion;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly RegistrarTransaccionAction $registrarTransaccion) {}

    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'comunasDisponibles' => config('juego.comunas_disponibles'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * Crea al jugador con la comuna inicial que eligió (RF-02) y le abona los
     * créditos iniciales configurados para que pueda construir su primer
     * edificio.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'comuna_inicial' => ['required', 'string', Rule::in(config('juego.comunas_disponibles'))],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'comuna_inicial' => $request->comuna_inicial,
        ]);

        $this->registrarTransaccion->ejecutar(
            jugador: $user,
            moneda: Moneda::Creditos,
            monto: config('juego.creditos_iniciales'),
            motivo: MotivoTransaccion::CreditoInicial,
        );

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
