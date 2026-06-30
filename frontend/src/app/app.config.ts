import { ApplicationConfig, provideZoneChangeDetection } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

import { routes } from './app.routes';
import { ApiService } from './services/api.service';

// Interceptor para detectar expiración de sesión (errores 401)
const authInterceptor = (req: any, next: any) => {
  const router = inject(Router);
  const api = inject(ApiService);
  return next(req).pipe(
    catchError((err) => {
      if (err.status === 401) {
        api.logout();
        router.navigate(['/admin/login']);
      }
      return throwError(() => err);
    })
  );
};

export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection({ eventCoalescing: true }), 
    provideRouter(routes),
    provideHttpClient(withInterceptors([authInterceptor]))
  ]
};
