import { Component, signal, inject, OnInit, OnDestroy, ViewChild, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, Router } from '@angular/router';
import { ApiService } from '../services/api.service';

declare const Chart: any;

@Component({
  selector: 'app-admin-dashboard',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  template: `
    <div class="dashboard-layout">
      <!-- Top Navigation Header -->
      <header class="header">
        <div class="logo-area">
          <img src="logo.png" alt="Logo" style="width: 38px; height: 38px; border-radius: 8px; object-fit: cover; box-shadow: 0 2px 8px rgba(255, 209, 0, 0.15);">
          <div class="logo-text">
            <h2>Clásicos La Fe</h2>
            <p>Panel de Administración</p>
          </div>
          <nav class="header-nav" style="display: flex; gap: 20px; margin-left: 40px;">
            <a routerLink="/admin/dashboard" routerLinkActive="active" style="color: #E31B23; text-decoration: none; font-size: 0.9rem; font-weight: 600;">Dashboard</a>
            <a routerLink="/admin/registros" routerLinkActive="active" style="color: #8e8e93; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: color 0.3s;" onmouseenter="this.style.color='#fff'" onmouseleave="this.style.color='#8e8e93'">Registros</a>
            <a routerLink="/admin/recargas" routerLinkActive="active" style="color: #8e8e93; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: color 0.3s;" onmouseenter="this.style.color='#fff'" onmouseleave="this.style.color='#8e8e93'">Recargas</a>
          </nav>
        </div>
        <div class="user-profile">
          <span class="user-badge">Admin</span>
          <button (click)="logout()" class="logout-btn">Cerrar Sesión ➔</button>
        </div>
      </header>

      <main class="main-content">
        <!-- Date filter header bar -->
        <div class="filter-header-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background-color: #121214; padding: 16px 24px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.05);">
          <h3 style="margin: 0; font-size: 1.1rem; color: #fff;">Resumen General</h3>
          <div style="display: flex; align-items: center; gap: 12px;">
            <input type="date" [(ngModel)]="startDate" class="form-control-date" style="padding: 8px 12px; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.85rem; outline: none;">
            <span style="color: #8e8e93; font-size: 0.9rem;">a</span>
            <input type="date" [(ngModel)]="endDate" class="form-control-date" style="padding: 8px 12px; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.85rem; outline: none;">
            <button class="btn-filter" (click)="loadDashboard()" style="padding: 8px 16px; background-color: #ff453a; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.85rem; transition: background 0.3s;">Filtrar</button>
            <button class="btn-reset" (click)="reset()" style="padding: 8px 16px; background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; cursor: pointer; font-size: 0.85rem; transition: background 0.3s;">Reestablecer</button>
          </div>
        </div>

        <!-- Statistics Counter Cards -->
        <section class="stats-grid">
          <div class="stat-card total">
            <h3>Total Participaciones</h3>
            <p class="number">{{ stats()?.cards?.total ?? '0' }}</p>
          </div>
          <div class="stat-card pending">
            <h3>Por Validar</h3>
            <p class="number">{{ stats()?.cards?.pendientes ?? '0' }}</p>
          </div>
          <div class="stat-card approved">
            <h3>Recargas Aprobadas</h3>
            <p class="number">{{ stats()?.cards?.aprobados ?? '0' }}</p>
          </div>
          <div class="stat-card rejected">
            <h3>Rechazados</h3>
            <p class="number">{{ stats()?.cards?.rechazados ?? '0' }}</p>
          </div>
          <div class="stat-card total" style="border-left: 4px solid #bf5af2;">
            <h3>Usuarios en Bot</h3>
            <p class="number">{{ stats()?.cards?.usuarios ?? '0' }}</p>
          </div>
        </section>

        <!-- Dashboard Charts & Top items Grid -->
        <section class="dashboard-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 40px;">
          <div class="table-card" style="padding: 24px;">
            <div class="table-header" style="margin-bottom: 20px;">
              <h3>Actividad de Participaciones</h3>
            </div>
            <div style="position: relative; height: 300px; width: 100%;">
              <canvas #chartCanvas></canvas>
            </div>
          </div>

          <div class="table-card" style="padding: 24px;">
            <div class="table-header" style="margin-bottom: 20px;">
              <h3>Compañías Telefónicas</h3>
            </div>
            <div *ngIf="!stats()?.top_telefonias?.length" style="color: #8e8e93; font-size: 0.9rem; text-align: center; padding: 40px 0;">
              {{ loading() ? 'Cargando...' : 'Sin datos disponibles' }}
            </div>
            <div *ngFor="let t of (stats()?.top_telefonias || [])" style="margin-bottom: 20px;">
              <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem;">
                <span style="font-weight: 600; color: #fff;">{{ t.Telefonia }}</span>
                <span style="font-weight: 700; color: #ff453a;">{{ t.total }} recargas</span>
              </div>
              <div style="height: 8px; background: rgba(255,255,255,0.05); border-radius: 4px; overflow: hidden;">
                <div [style.width.%]="maxTelefonia() ? (t.total / maxTelefonia() * 100) : 0"
                     style="height: 100%; background: linear-gradient(90deg, #ff453a, #ff9f0a); border-radius: 4px;"></div>
              </div>
            </div>
          </div>
        </section>

        <!-- Recent Activity Table -->
        <section class="table-card">
          <div class="table-header">
            <h3>Actividad Reciente</h3>
            <button routerLink="/admin/registros" style="padding: 8px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 10px; cursor: pointer; font-size: 0.85rem; transition: background 0.3s;">Ver Todos los Registros</button>
          </div>

          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Usuario</th>
                  <th>Celular</th>
                  <th>Telefonia</th>
                  <th>Estatus</th>
                  <th>Fecha Registro</th>
                </tr>
              </thead>
              <tbody>
                <tr *ngFor="let r of (stats()?.recent || [])">
                  <td><code style="color: #ff9f0a;">#{{ r.idRegistro }}</code></td>
                  <td><span style="font-weight: 600; color: #fff;">{{ r.Nombre || 'Participante' }}</span></td>
                  <td>{{ r.Celular }}</td>
                  <td><span class="user-badge" style="background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1);">{{ r.Telefonia || '—' }}</span></td>
                  <td>
                    <span class="status-badge" [ngClass]="getStatusClass(r.Estatus)">
                      {{ getStatusText(r.Estatus) }}
                    </span>
                  </td>
                  <td>{{ r.FechaRegistro | date:'dd/MM/yyyy HH:mm' }}</td>
                </tr>
                <tr *ngIf="!loading() && !stats()?.recent?.length">
                  <td colspan="6" style="text-align: center; color: #8e8e93; padding: 30px;">
                    Sin actividad reciente.
                  </td>
                </tr>
              </tbody>
            </table>
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
    .fire-icon {
      font-size: 2rem;
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
    .stat-card.pending { border-left: 4px solid #FFD100; }
    .stat-card.approved { border-left: 4px solid #00A753; }
    .stat-card.rejected { border-left: 4px solid #E31B23; }

    .table-card {
      background-color: #2B1D0C;
      border-radius: 20px;
      border: 1px solid rgba(255, 209, 0, 0.1);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      padding: 24px;
    }
    .table-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
    }
    .table-header h3 {
      font-size: 1.15rem;
      margin: 0;
      color: #fff;
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
      color: #aeaeb2;
    }
    .status-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .status-badge.pending { background: rgba(255, 209, 0, 0.15); color: #FFD100; }
    .status-badge.approved { background: rgba(0, 167, 83, 0.15); color: #00A753; }
    .status-badge.rejected { background: rgba(227, 27, 35, 0.15); color: #E31B23; }
    .status-badge.completed { background: rgba(0, 167, 83, 0.15); color: #00A753; }
    .status-badge.process { background: rgba(0, 155, 224, 0.15); color: #009BE0; }
    .form-control-date::-webkit-calendar-picker-indicator {
      filter: invert(1);
      cursor: pointer;
    }
    .btn-filter:hover {
      background-color: #c4141c !important;
    }
    .btn-reset:hover {
      background: rgba(255,255,255,0.1) !important;
    }
  `]
})
export class AdminDashboardComponent implements OnInit, OnDestroy {
  private api = inject(ApiService);
  private router = inject(Router);

  @ViewChild('chartCanvas') chartCanvas!: ElementRef<HTMLCanvasElement>;

  stats = signal<any>(null);
  loading = signal(true);
  startDate = '';
  endDate = '';
  private chart: any = null;

  ngOnInit() {
    if (!(window as any).Chart) {
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
      s.onload = () => this.loadDashboard();
      document.head.appendChild(s);
    } else {
      this.loadDashboard();
    }
  }

  ngOnDestroy() {
    if (this.chart) {
      this.chart.destroy();
      this.chart = null;
    }
  }

  maxTelefonia(): number {
    const t = this.stats()?.top_telefonias || [];
    return t.length ? Math.max(...t.map((x: any) => x.total)) : 1;
  }

  loadDashboard() {
    this.loading.set(true);
    const params: any = {};
    if (this.startDate) params.start_date = this.startDate;
    if (this.endDate)   params.end_date   = this.endDate;

    this.api.getDashboard(params).subscribe({
      next: data => {
        this.stats.set(data);
        this.loading.set(false);
        setTimeout(() => this.renderChart(data.chart || []), 100);
      },
      error: () => this.loading.set(false)
    });
  }

  reset() {
    this.startDate = '';
    this.endDate = '';
    this.loadDashboard();
  }

  private renderChart(chartData: any[]) {
    if (!this.chartCanvas?.nativeElement) return;
    const ChartJs = (window as any).Chart;
    if (!ChartJs) return;
    if (this.chart) {
      this.chart.destroy();
      this.chart = null;
    }
    this.chart = new ChartJs(this.chartCanvas.nativeElement, {
      type: 'bar',
      data: {
        labels: chartData.map((d: any) => d.fecha),
        datasets: [
          { label: 'Total',      data: chartData.map((d: any) => +d.total),      backgroundColor: 'rgba(0, 155, 224, 0.15)', borderColor: '#009BE0', borderWidth: 2, borderRadius: 4 },
          { label: 'Aprobados (Recarga)',  data: chartData.map((d: any) => +d.aprobados),  backgroundColor: 'rgba(0, 167, 83, 0.2)',    borderColor: '#00A753', borderWidth: 2, borderRadius: 4 },
          { label: 'Rechazados', data: chartData.map((d: any) => +d.rechazados), backgroundColor: 'rgba(227, 27, 35, 0.15)',   borderColor: '#E31B23', borderWidth: 2, borderRadius: 4 },
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: '#aeaeb2',
              font: { family: 'Inter', size: 12 }
            }
          }
        },
        scales: {
          x: {
            grid: { color: 'rgba(255, 255, 255, 0.05)' },
            ticks: { color: '#8e8e93' }
          },
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(255, 255, 255, 0.05)' },
            ticks: { color: '#8e8e93', stepSize: 1 }
          }
        }
      }
    });
  }

  getStatusText(status: number): string {
    switch (status) {
      case 0: return 'Incompleto';
      case 1: return 'Por validar';
      case 2: return 'Aprobado';
      case 3: return 'Rechazado';
      case 4: return 'Canjeado';
      case 5: return 'En Recarga';
      default: return 'Desconocido';
    }
  }

  getStatusClass(status: number): string {
    switch (status) {
      case 0: return 'pending';
      case 1: return 'pending';
      case 2: return 'approved';
      case 3: return 'rejected';
      case 4: return 'completed';
      case 5: return 'process';
      default: return '';
    }
  }

  logout() {
    this.api.logout();
    this.router.navigate(['/admin/login']);
  }
}
