import { Routes } from '@angular/router';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from './services/api.service';

// Guard para proteger rutas de administración
const adminGuard = () => {
  const api = inject(ApiService);
  const router = inject(Router);
  if (api.isLoggedIn()) {
    return true;
  }
  router.navigate(['/admin/login']);
  return false;
};

export const routes: Routes = [
  { 
    path: '', 
    redirectTo: 'admin/dashboard', 
    pathMatch: 'full' 
  },
  { 
    path: 'admin/login', 
    loadComponent: () => import('./components/admin-login.component').then(m => m.AdminLoginComponent) 
  },
  { 
    path: 'admin/dashboard', 
    loadComponent: () => import('./components/admin-dashboard.component').then(m => m.AdminDashboardComponent),
    canActivate: [adminGuard]
  },
  { 
    path: 'admin/registros', 
    loadComponent: () => import('./components/admin-registros.component').then(m => m.AdminRegistrosComponent),
    canActivate: [adminGuard]
  },
  { 
    path: '**', 
    redirectTo: 'admin/dashboard' 
  }
];
