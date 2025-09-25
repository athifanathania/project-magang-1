<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\{TextInput, Select, Toggle, TagsInput};
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class MyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Profile User';
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static bool $shouldRegisterNavigation = false; // jangan tampil di sidebar
    protected static string $view = 'filament.pages.my-profile';

    public ?array $data = [];

    public ?string $backUrl = null;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);

        // simpan url asal dari query, fallback ke previous()
        $this->backUrl = request('back') ?: url()->previous();

        // hindari loop kalau asalnya halaman ini juga
        if ($this->backUrl && str_contains($this->backUrl, static::getUrl())) {
            $this->backUrl = null;
        }

        $u = auth()->user();
        $this->form->fill([
            'name'       => $u->name,
            'email'      => $u->email,
            'department' => $u->department,
            'roles'      => method_exists($u, 'getRoleNames') ? $u->getRoleNames()->toArray() : [],
            'is_active'  => (bool) ($u->is_active ?? true),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->disabled()          // read-only
                    ->dehydrated(false),  // jangan disimpan

                Select::make('department')
                    ->label('Departemen')
                    ->options([
                        'QC' => 'QC','PPC' => 'PPC','Produksi' => 'Produksi',
                        'Audit Internal' => 'Audit Internal','HRD' => 'HRD',
                        'Purchasing' => 'Purchasing','Marketing' => 'Marketing',
                        'Maintenance' => 'Maintenance','Engineering' => 'Engineering',
                        'Finance' => 'Finance','IT' => 'IT',
                    ])
                    ->searchable(),

                TagsInput::make('roles')
                    ->label('Roles')
                    ->disabled()          // read-only
                    ->dehydrated(false),

                Toggle::make('is_active')
                    ->label('Status Aktif')
                    ->disabled()          // read-only
                    ->dehydrated(false),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $u = auth()->user();
        $u->fill([
            'name'       => $this->data['name']       ?? $u->name,
            'department' => $this->data['department'] ?? $u->department,
        ])->save();

        // notifikasi akan tetap muncul setelah redirect
        Notification::make()->title('Profil berhasil diperbarui')->success()->send();

        // redirect ke halaman asal; fallback ke dashboard panel admin
        $fallback = route('filament.admin.pages.dashboard');
        $this->redirect($this->backUrl ?: $fallback, navigate: true);
    }
}
