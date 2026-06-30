import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { ApiService } from '../services/api.service';

interface RecargaLog {
  idLog: number;
  idRegistro: number | null;
  Mensaje: string;
  Codigo: string;
  Folio: string | null;
  FechaRegistro: string;
  CodigoUnico: string | null;
  TelefonoRecarga: string | null;
  Celular: string | null;
  NombreUsuario: string | null;
  Telefonia: string | null;
  Monto: number | null;
}

@Component({
  selector: 'app-admin-recargas',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  template: `
    <div class="dashboard-layout">
      <!-- Top Navigation Header -->
      <header class="header">
        <div class="logo-area">
          <img src="/logo.png" alt="Logo" style="width: 38px; height: 38px; border-radius: 8px; object-fit: cover; box-shadow: 0 2px 8px rgba(255, 209, 0, 0.15);">
          <div class="logo-text">
            <h2>Clásicos La Fe</h2>
            <p>Panel de Administración</p>
          </div>
          <nav class="header-nav" style="display: flex; gap: 20px; margin-left: 40px;">
            <a routerLink="/admin/dashboard" routerLinkActive="active" style="color: #8e8e93; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: color 0.3s;" onmouseenter="this.style.color='#fff'" onmouseleave="this.style.color='#8e8e93'">Dashboard</a>
            <a routerLink="/admin/registros" routerLinkActive="active" style="color: #8e8e93; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: color 0.3s;" onmouseenter="this.style.color='#fff'" onmouseleave="this.style.color='#8e8e93'">Registros</a>
            <a routerLink="/admin/recargas" routerLinkActive="active" style="color: #ff453a; text-decoration: none; font-size: 0.9rem; font-weight: 600;">Recargas</a>
          </nav>
        </div>
        <div class="user-profile">
          <span class="user-badge">Admin</span>
          <button (click)="logout()" class="logout-btn">Cerrar Sesión ➔</button>
        </div>
      </header>

      <main class="main-content">
        <!-- Header Section -->
        <div class="page-title-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
          <h2 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #fff;">Historial de Recargas</h2>
          <div style="display: flex; gap: 12px;">
            <button (click)="exportToCSV()" class="refresh-btn" style="background: rgba(255, 209, 0, 0.2); border-color: rgba(255, 209, 0, 0.4); color: #FFD100; font-weight: 600;">
              📥 Exportar CSV
            </button>
            <button (click)="loadData()" class="refresh-btn" [disabled]="loading()">
              {{ loading() ? 'Cargando...' : '🔄 Actualizar Historial' }}
            </button>
          </div>
        </div>

        <!-- Filter / Stats Bar -->
        <section class="stats-grid">
          <div class="stat-card total">
            <h3>Intentos de Recarga</h3>
            <p class="number">{{ totalCount() }}</p>
          </div>
          <div class="stat-card approved">
            <h3>Recargas Exitosas</h3>
            <p class="number">{{ successCount() }}</p>
          </div>
          <div class="stat-card rejected">
            <h3>Recargas Fallidas</h3>
            <p class="number">{{ failCount() }}</p>
          </div>
        </section>

        <!-- Recargas Log List -->
        <section class="table-card">
          <div class="table-wrapper">
            <table *ngIf="recargas().length > 0; else noData">
              <thead>
                <tr>
                  <th>Usuario / Participante</th>
                  <th>Celular Recarga</th>
                  <th>Compañía</th>
                  <th>Estatus</th>
                  <th>Folio Taecel</th>
                  <th>Monto</th>
                  <th>Fecha Registro</th>
                </tr>
              </thead>
              <tbody>
                <tr *ngFor="let rec of recargas()">
                  <td>
                    <div class="user-cell">
                      <span class="name">{{ rec.NombreUsuario || 'Participante' }}</span>
                      <span class="phone" *ngIf="rec.Celular">De: {{ rec.Celular }}</span>
                    </div>
                  </td>
                  <td>
                    <div class="phone-cell" style="display: flex; flex-direction: column;">
                      <span style="font-weight: 600; color: #fff;">{{ rec.TelefonoRecarga || '—' }}</span>
                      <span style="font-size: 0.75rem; color: #8e8e93;" *ngIf="rec.CodigoUnico">Código: {{ rec.CodigoUnico }}</span>
                    </div>
                  </td>
                  <td>
                    <span class="carrier-badge">{{ rec.Telefonia || '—' }}</span>
                  </td>
                  <td>
                    <span 
                      class="status-badge" 
                      [ngClass]="rec.Codigo === '0' ? 'approved' : 'rejected'"
                      [title]="rec.Codigo === '0' ? 'Recarga procesada correctamente' : rec.Mensaje">
                      {{ rec.Codigo === '0' ? '✓ Exitosa' : '✕ No Exitosa ℹ' }}
                    </span>
                  </td>
                  <td>
                    <span class="folio-text" [class.no-folio]="!rec.Folio">{{ rec.Folio || '—' }}</span>
                  </td>
                  <td>
                    <span style="font-weight: 600; color: #FFD100;">{{ rec.Monto ? '$' + rec.Monto : '—' }}</span>
                  </td>
                  <td>
                    <span style="color: #aeaeb2;">{{ rec.FechaRegistro | date:'dd/MM/yyyy HH:mm:ss' }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
            <ng-template #noData>
              <div class="empty-state">
                <span class="empty-icon">⚡</span>
                <p>No se han registrado transacciones de recargas en el historial.</p>
              </div>
            </ng-template>
          </div>
        </section>
      </main>
    </div>
  `,
  styles: [`
    .dashboard-layout {
      min-height: 100vh;
      background-color: #1A0F00;
      color: #f5f5f5;
      font-family: 'Inter', sans-serif;
    }
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 40px;
      background-color: #2B1D0C;
      border-bottom: 1px solid rgba(255, 209, 0, 0.2);
    }
    .logo-area {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .logo-text h2 {
      font-size: 1.25rem;
      margin: 0;
      font-weight: 700;
      color: #fff;
    }
    .logo-text p {
      font-size: 0.8rem;
      color: #aeaeb2;
      margin: 0;
    }
    .user-profile {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .user-badge {
      font-size: 0.75rem;
      background: rgba(255, 209, 0, 0.15);
      color: #FFD100;
      padding: 4px 10px;
      border-radius: 20px;
      font-weight: 600;
      border: 1px solid rgba(255, 209, 0, 0.2);
    }
    .logout-btn {
      background: transparent;
      border: none;
      color: #aeaeb2;
      font-size: 0.9rem;
      cursor: pointer;
      transition: color 0.3s;
    }
    .logout-btn:hover {
      color: #E31B23;
    }
    .main-content {
      padding: 40px;
      max-width: 1400px;
      margin: 0 auto;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 24px;
      margin-bottom: 40px;
    }
    .stat-card {
      background-color: #2B1D0C;
      border: 1px solid rgba(255, 209, 0, 0.1);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
    }
    .stat-card h3 {
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #aeaeb2;
      margin: 0 0 12px 0;
    }
    .stat-card .number {
      font-size: 2rem;
      font-weight: 700;
      margin: 0;
      color: #fff;
    }
    .stat-card.total { border-left: 4px solid #009BE0; }
    .stat-card.approved { border-left: 4px solid #00A753; }
    .stat-card.rejected { border-left: 4px solid #E31B23; }
    
    .table-card {
      background-color: #2B1D0C;
      border-radius: 20px;
      border: 1px solid rgba(255, 209, 0, 0.1);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      padding: 24px;
    }
    .refresh-btn {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #fff;
      padding: 8px 16px;
      border-radius: 10px;
      cursor: pointer;
      font-size: 0.85rem;
      transition: all 0.3s;
    }
    .refresh-btn:hover:not(:disabled) {
      background: rgba(255, 255, 255, 0.1);
    }
    .table-wrapper {
      overflow-x: auto;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }
    th {
      padding: 16px;
      border-bottom: 1px solid rgba(255, 209, 0, 0.1);
      color: #aeaeb2;
      font-size: 0.85rem;
      text-transform: uppercase;
      font-weight: 600;
    }
    td {
      padding: 16px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.02);
      font-size: 0.9rem;
      vertical-align: middle;
    }
    .user-cell {
      display: flex;
      flex-direction: column;
    }
    .user-cell .name {
      font-weight: 600;
      color: #fff;
    }
    .user-cell .phone {
      color: #aeaeb2;
      font-size: 0.8rem;
    }
    .carrier-badge {
      background: rgba(255, 255, 255, 0.05);
      color: #fff;
      border: 1px solid rgba(255, 255, 255, 0.1);
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 0.8rem;
      font-weight: 600;
    }
    .status-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      cursor: help;
    }
    .status-badge.approved { background: rgba(0, 167, 83, 0.15); color: #00A753; }
    .status-badge.rejected { background: rgba(227, 27, 35, 0.15); color: #E31B23; }
    
    .code-value {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      padding: 3px 8px;
      border-radius: 4px;
      font-family: monospace;
      color: #aeaeb2;
      font-size: 0.85rem;
    }
    .folio-text {
      font-weight: 600;
      color: #fff;
    }
    .folio-text.no-folio {
      color: #48484a;
      font-weight: normal;
    }
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #8e8e93;
    }
    .empty-icon { font-size: 3rem; margin-bottom: 12px; display: block; }
  `]
})
export class AdminRecargasComponent implements OnInit {
  private api = inject(ApiService);
  private router = inject(Router);

  recargas = signal<RecargaLog[]>([]);
  loading = signal(false);

  totalCount = computed(() => this.recargas().length);
  successCount = computed(() => this.recargas().filter(r => r.Codigo === '0').length);
  failCount = computed(() => this.recargas().filter(r => r.Codigo !== '0').length);

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.loading.set(true);
    this.api.getRecargas().subscribe({
      next: (res: any) => {
        if (res.success) {
          this.recargas.set(res.data as RecargaLog[]);
        }
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  exportToCSV() {
    const data = this.recargas();
    if (data.length === 0) {
      alert('No hay datos para exportar.');
      return;
    }
    
    // Definir encabezados
    const headers = [
      'Usuario',
      'Celular Participante',
      'Celular Recarga',
      'Codigo Unico',
      'Compania',
      'Monto',
      'Estatus',
      'Folio Recarga',
      'Mensaje',
      'Fecha Registro'
    ];
    
    // Construir contenido CSV
    const csvRows = [];
    csvRows.push(headers.join(','));
    
    for (const rec of data) {
      const row = [
        rec.NombreUsuario || 'Participante',
        rec.Celular || '',
        rec.TelefonoRecarga || '',
        rec.CodigoUnico || '',
        rec.Telefonia || '',
        rec.Monto ? `$${rec.Monto}` : '',
        rec.Codigo === '0' ? 'Exitosa' : 'Fallida',
        rec.Folio || '',
        (rec.Mensaje || '').replace(/"/g, '""'), // Escapar comillas
        rec.FechaRegistro
      ];
      csvRows.push(row.map(val => `"${val}"`).join(','));
    }
    
    // Añadir BOM para compatibilidad con Excel (UTF-8)
    const csvContent = "\uFEFF" + csvRows.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `historial_recargas_${new Date().toISOString().slice(0,10)}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  logout() {
    this.api.logout();
    this.router.navigate(['/admin/login']);
  }
}
