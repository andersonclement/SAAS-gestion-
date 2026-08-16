@extends('layouts.admin')

@section('title', __('Administrateurs'))

@section('content')
    <h1 style="margin-top:0;">{{ __('Administrateurs') }}</h1>

    <p><a class="btn" href="{{ route('admin.admins.create') }}">+ {{ __('Nouvel administrateur') }}</a></p>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>{{ __('Nom') }}</th>
                    <th>{{ __('E-mail') }}</th>
                    <th>{{ __('Créé le') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($admins as $admin)
                    <tr>
                        <td>{{ $admin->name }}</td>
                        <td>{{ $admin->email }}</td>
                        <td>{{ $admin->created_at->format('d/m/Y') }}</td>
                        <td>
                            @unless ($admin->id === auth('admin')->id())
                                <form method="POST" action="{{ route('admin.admins.destroy', $admin) }}" data-confirm="{{ __('Supprimer cet administrateur ?') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger" style="padding:.3rem .6rem;font-size:.8rem;">{{ __('Supprimer') }}</button>
                                </form>
                            @else
                                <span class="badge" style="background:#eef2ff;color:#3730a3;">{{ __('Vous') }}</span>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
