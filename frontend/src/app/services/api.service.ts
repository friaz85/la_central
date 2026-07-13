import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  // En SiteGround, el backend PHP estará en el subdirectorio /backend
  private apiBaseUrl = 'backend'; 

  constructor(private http: HttpClient) {
    // Si estamos en local (localhost:4200), podemos apuntar al host local PHP
    if (window.location.hostname === 'localhost') {
      this.apiBaseUrl = 'http://localhost/g15k/backend';
    } else {
      // Dinámico basado en el host de producción
      this.apiBaseUrl = `${window.location.origin}/backend`;
    }
  }

  private getAuthHeaders(): HttpHeaders {
    const token = localStorage.getItem('admin_token') || '';
    return new HttpHeaders({
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    });
  }

  // --- Endpoints Administrativos ---

  login(data: any): Observable<any> {
    return this.http.post(`${this.apiBaseUrl}/api/login.php`, data);
  }

  logout(): void {
    localStorage.removeItem('admin_token');
    localStorage.removeItem('admin_user');
  }

  isLoggedIn(): boolean {
    return !!localStorage.getItem('admin_token');
  }

  getRegistros(): Observable<any> {
    return this.http.get(`${this.apiBaseUrl}/api/registros.php`, { headers: this.getAuthHeaders() });
  }

  checkFolio(folio: string, excludeId: number = 0): Observable<any> {
    return this.http.get(`${this.apiBaseUrl}/api/registros.php?check_folio=${encodeURIComponent(folio)}&exclude_id=${excludeId}`, { headers: this.getAuthHeaders() });
  }


  aprobarRegistro(idRegistro: number, payload: any): Observable<any> {
    return this.http.post(`${this.apiBaseUrl}/api/registros.php`, {
      idRegistro,
      accion: 'aprobar',
      ...payload
    }, { headers: this.getAuthHeaders() });
  }

  rechazarRegistro(idRegistro: number, motivo: string): Observable<any> {
    return this.http.post(`${this.apiBaseUrl}/api/registros.php`, {
      idRegistro,
      accion: 'rechazar',
      motivo
    }, { headers: this.getAuthHeaders() });
  }

  getCadenas(): Observable<any> {
    return this.http.get(`${this.apiBaseUrl}/api/cadenas.php`, { headers: this.getAuthHeaders() });
  }

  getSucursales(idCadena?: number): Observable<any> {
    const url = idCadena ? `${this.apiBaseUrl}/api/sucursales.php?idCadena=${idCadena}` : `${this.apiBaseUrl}/api/sucursales.php`;
    return this.http.get(url, { headers: this.getAuthHeaders() });
  }

  getProductos(): Observable<any> {
    return this.http.get(`${this.apiBaseUrl}/api/productos.php`, { headers: this.getAuthHeaders() });
  }

  getDashboard(params?: any): Observable<any> {
    const token = localStorage.getItem('admin_token') || '';
    const headers = new HttpHeaders({
      'Authorization': `Bearer ${token}`
    });
    return this.http.get(`${this.apiBaseUrl}/api/dashboard.php`, { headers, params });
  }

  getRecargas(): Observable<any> {
    return this.http.get(`${this.apiBaseUrl}/api/recargas.php`, { headers: this.getAuthHeaders() });
  }

  // --- Endpoints Públicos de Canje ---
  
  getTelefonias(): Observable<any> {
    return this.http.get(`${this.apiBaseUrl}/api/telefonias.php`);
  }

  validateToken(token: string): Observable<any> {
    return this.http.get(`${this.apiBaseUrl}/api/canje.php?token=${token}`);
  }

  redeemRecarga(data: { token: string; idTelefonia: number; telefono: string }): Observable<any> {
    return this.http.post(`${this.apiBaseUrl}/api/canje.php`, data);
  }
}
