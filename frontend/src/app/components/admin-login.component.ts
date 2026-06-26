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
      <div class="login-card">
        <div class="login-header">
          <img src="logo.png" alt="Logo La Central" style="width: 100px; height: 100px; border-radius: 16px; margin-bottom: 15px; box-shadow: 0 4px 15px rgba(255, 209, 0, 0.2); object-fit: cover;">
          <h1>Clásicos La Fe</h1>
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
      </div>
    </div>
  `,
  styles: [`
    .login-page {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      background: radial-gradient(circle at top right, #3E2723 0%, #1A0F00 100%);
      font-family: 'Outfit', 'Inter', sans-serif;
      padding: 20px;
      color: #f5f5f5;
    }
    .login-card {
      width: 100%;
      max-width: 420px;
      background: rgba(43, 29, 12, 0.7);
      border: 1px solid rgba(255, 209, 0, 0.25);
      border-radius: 20px;
      padding: 40px;
      backdrop-filter: blur(16px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.7);
      animation: fadeIn 0.6s ease-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(15px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .login-header {
      text-align: center;
      margin-bottom: 30px;
    }
    .logo-badge {
      font-size: 3rem;
      margin-bottom: 15px;
      display: inline-block;
      animation: float 3s ease-in-out infinite;
    }
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-8px); }
    }
    .login-header h1 {
      font-size: 1.8rem;
      margin: 0 0 8px 0;
      font-weight: 700;
      letter-spacing: -0.5px;
      background: linear-gradient(135deg, #FFD100 0%, #E31B23 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .login-header .subtitle {
      font-size: 0.9rem;
      color: #aeaeb2;
      margin: 0;
    }
    .error-box {
      background: rgba(227, 27, 35, 0.15);
      border: 1px solid rgba(227, 27, 35, 0.3);
      padding: 12px 16px;
      border-radius: 12px;
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
      font-size: 0.85rem;
      font-weight: 500;
      color: #aeaeb2;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }
    .input-icon {
      position: absolute;
      left: 14px;
      font-size: 1.1rem;
      color: #FFD100;
    }
    .input-wrapper input {
      width: 100%;
      padding: 14px 14px 14px 44px;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      font-size: 1rem;
      color: #fff;
      outline: none;
      transition: all 0.3s ease;
    }
    .input-wrapper input:focus {
      background: rgba(255, 255, 255, 0.08);
      border-color: #FFD100;
      box-shadow: 0 0 10px rgba(255, 209, 0, 0.2);
    }
    .submit-btn {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, #FFD100 0%, #E31B23 100%);
      border: none;
      border-radius: 12px;
      color: #1A0F00;
      font-size: 1rem;
      font-weight: 750;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 10px;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .submit-btn:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(255, 209, 0, 0.4);
    }
    .submit-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    .loader {
      width: 20px;
      height: 20px;
      border: 3px solid rgba(25, 15, 0, 0.3);
      border-radius: 50%;
      border-top-color: #1A0F00;
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
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
