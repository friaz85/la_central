import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ApiService } from '../services/api.service';

@Component({
  selector: 'app-admin-login',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="login-page">
      <!-- Animated background stripes -->
      <div class="bg-stripe bg-stripe-1"></div>
      <div class="bg-stripe bg-stripe-2"></div>

      <div class="login-card">
        <div class="login-header">
          <div class="logo-badge">
            <img src="/logo.png" alt="Gatorade G15K" class="logo-img">
          </div>
          <h1>Gatorade G15K</h1>
          <p class="subtitle">Panel de Control Administrativo</p>
        </div>

        <div class="error-box" *ngIf="error()">
          <span class="error-icon">⚠️</span> {{ error() }}
        </div>

        <form (ngSubmit)="onSubmit()">
          <div class="form-group">
            <label for="username">Usuario</label>
            <div class="input-wrapper">
              <span class="input-icon">👤</span>
              <input
                id="username"
                type="text"
                [(ngModel)]="username"
                name="username"
                placeholder="Ingresa tu usuario"
                required
                autocomplete="username">
            </div>
          </div>

          <div class="form-group">
            <label for="password">Contraseña</label>
            <div class="input-wrapper">
              <span class="input-icon">🔒</span>
              <input
                id="password"
                type="password"
                [(ngModel)]="password"
                name="password"
                placeholder="••••••••"
                required
                autocomplete="current-password">
            </div>
          </div>

          <button type="submit" class="submit-btn" [disabled]="loading()">
            <span *ngIf="!loading()">Acceder al Panel</span>
            <span *ngIf="loading()" class="loader"></span>
          </button>
        </form>

        <div class="powered-by">
          <span>⚡ Gatorade® G15K 2026 — CDMX</span>
        </div>
      </div>
    </div>
  `,
  styles: [`
    :host {
      display: block;
    }
    .login-page {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      background: #0d0d0d;
      font-family: 'Inter', 'Outfit', sans-serif;
      padding: 20px;
      color: #f5f5f5;
      position: relative;
      overflow: hidden;
    }
    /* Gatorade decorative diagonal stripes */
    .bg-stripe {
      position: absolute;
      top: 0;
      bottom: 0;
      width: 40vw;
      pointer-events: none;
    }
    .bg-stripe-1 {
      left: -10vw;
      background: linear-gradient(160deg, rgba(255, 102, 0, 0.07) 0%, transparent 70%);
      transform: skewX(-10deg);
    }
    .bg-stripe-2 {
      right: -10vw;
      background: linear-gradient(200deg, rgba(0, 130, 74, 0.05) 0%, transparent 70%);
      transform: skewX(10deg);
    }
    .login-card {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
      background: #1a1a1a;
      border: 1px solid rgba(255, 102, 0, 0.2);
      border-radius: 20px;
      padding: 44px 40px 32px;
      box-shadow: 0 24px 64px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255,102,0,0.05);
      animation: fadeIn 0.5s ease-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .login-header {
      text-align: center;
      margin-bottom: 32px;
    }
    .logo-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 80px;
      height: 80px;
      border-radius: 20px;
      background: rgba(255, 102, 0, 0.1);
      border: 1px solid rgba(255, 102, 0, 0.25);
      margin-bottom: 18px;
      box-shadow: 0 0 30px rgba(255, 102, 0, 0.15);
    }
    .logo-img {
      width: 56px;
      height: 56px;
      object-fit: contain;
      border-radius: 10px;
    }
    .login-header h1 {
      font-size: 1.8rem;
      margin: 0 0 6px;
      font-weight: 800;
      letter-spacing: -0.5px;
      background: linear-gradient(135deg, #FF6600 0%, #FF9933 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .login-header .subtitle {
      font-size: 0.88rem;
      color: #6e6e73;
      margin: 0;
      letter-spacing: 0.2px;
    }
    .error-box {
      background: rgba(227, 27, 35, 0.12);
      border: 1px solid rgba(227, 27, 35, 0.3);
      padding: 12px 16px;
      border-radius: 10px;
      color: #ff453a;
      font-size: 0.85rem;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .form-group {
      margin-bottom: 20px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .form-group label {
      font-size: 0.78rem;
      font-weight: 600;
      color: #8e8e93;
      text-transform: uppercase;
      letter-spacing: 0.8px;
    }
    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }
    .input-icon {
      position: absolute;
      left: 14px;
      font-size: 1rem;
      color: #FF6600;
      pointer-events: none;
    }
    .input-wrapper input {
      width: 100%;
      padding: 13px 14px 13px 44px;
      background: #111;
      border: 1.5px solid rgba(255, 255, 255, 0.08);
      border-radius: 12px;
      font-size: 0.95rem;
      color: #fff;
      outline: none;
      transition: all 0.25s ease;
      box-sizing: border-box;
    }
    .input-wrapper input:focus {
      background: #0d0d0d;
      border-color: #FF6600;
      box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.15);
    }
    .input-wrapper input::placeholder { color: #48484a; }
    .submit-btn {
      width: 100%;
      padding: 15px;
      margin-top: 8px;
      background: linear-gradient(135deg, #FF6600 0%, #E85500 100%);
      border: none;
      border-radius: 12px;
      color: #fff;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.25s ease;
      display: flex;
      justify-content: center;
      align-items: center;
      letter-spacing: 0.3px;
      box-shadow: 0 4px 20px rgba(255, 102, 0, 0.3);
    }
    .submit-btn:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 8px 28px rgba(255, 102, 0, 0.45);
    }
    .submit-btn:active:not(:disabled) {
      transform: translateY(0);
    }
    .submit-btn:disabled {
      opacity: 0.55;
      cursor: not-allowed;
    }
    .loader {
      width: 20px;
      height: 20px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: #fff;
      animation: spin 0.75s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    .powered-by {
      text-align: center;
      margin-top: 24px;
      font-size: 0.75rem;
      color: #3a3a3c;
    }
  `]
})
export class AdminLoginComponent {
  private api = inject(ApiService);
  private router = inject(Router);

  username = '';
  password = '';
  loading = signal(false);
  error = signal('');

  onSubmit() {
    if (!this.username || !this.password) return;
    this.loading.set(true);
    this.error.set('');

    this.api.login({ username: this.username, password: this.password }).subscribe({
      next: (res: any) => {
        if (res.token) {
          localStorage.setItem('admin_token', res.token);
          localStorage.setItem('admin_user', JSON.stringify(res.user));
          this.router.navigate(['/admin/registros']);
        }
      },
      error: (err: any) => {
        this.error.set(err?.error?.error || 'Ocurrió un error al iniciar sesión.');
        this.loading.set(false);
      }
    });
  }
}
