import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { ApiService } from '../services/api.service';

interface Registro {
  idRegistro: number;
  idUsuario: number;
  Celular: string;
  NombreUsuario: string;
  Token: string;
  Estatus: number;
  EstatusDescarga: number;
  FotoCajas: string;
  FotoCajasUrl: string;
  CodigoUnico: string;
  MotivoRechazo: string | null;
  TelefonoRecarga: string | null;
  idTelefonia: number | null;
  FolioRecarga: string | null;
  TransID: string | null;
  Saldo_Final: string | null;
  FechaRegistro: string;
  FechaValidacion: string | null;
}

@Component({
  selector: 'app-admin-registros',
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
            <a routerLink="/admin/registros" routerLinkActive="active" style="color: #ff453a; text-decoration: none; font-size: 0.9rem; font-weight: 600;">Registros</a>
            <a routerLink="/admin/recargas" routerLinkActive="active" style="color: #8e8e93; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: color 0.3s;" onmouseenter="this.style.color='#fff'" onmouseleave="this.style.color='#8e8e93'">Recargas</a>
          </nav>
        </div>
        <div class="user-profile">
          <span class="user-badge">Admin</span>
          <button (click)="logout()" class="logout-btn">Cerrar Sesión ➔</button>
        </div>
      </header>

      <main class="main-content">
        <!-- Statistics Counter Cards -->
        <section class="stats-grid">
          <div class="stat-card total">
            <h3>Total Registros</h3>
            <p class="number">{{ stats().total }}</p>
          </div>
          <div class="stat-card pending">
            <h3>Por Validar</h3>
            <p class="number">{{ stats().pending }}</p>
          </div>
          <div class="stat-card approved">
            <h3>Aprobados</h3>
            <p class="number">{{ stats().approved }}</p>
          </div>
          <div class="stat-card rejected">
            <h3>Rechazados</h3>
            <p class="number">{{ stats().rejected }}</p>
          </div>
        </section>

        <!-- Registries List -->
        <section class="table-card">
          <div class="table-header">
            <h3>Listado de Participaciones</h3>
            <button (click)="loadData()" class="refresh-btn" [disabled]="loading()">
              {{ loading() ? 'Cargando...' : '🔄 Actualizar' }}
            </button>
          </div>

          <div class="table-wrapper">
            <table *ngIf="pendingRegistros().length > 0; else noData">
              <thead>
                <tr>
                  <th>Usuario / Celular</th>
                  <th>Código Asignado</th>
                  <th>Foto Evidencia</th>
                  <th>Fecha Registro</th>
                  <th>Estatus</th>
                  <th>Recarga Info</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr *ngFor="let reg of pendingRegistros()">
                  <td>
                    <div class="user-cell">
                      <span class="name">{{ reg.NombreUsuario || 'Participante' }}</span>
                      <span class="phone">{{ reg.Celular }}</span>
                    </div>
                  </td>
                  <td><code class="code-badge">{{ reg.CodigoUnico }}</code></td>
                  <td>
                    <div class="image-preview" (click)="openLightbox(reg.FotoCajasUrl)">
                      <img [src]="reg.FotoCajasUrl" alt="Foto Cajas">
                      <div class="hover-overlay">🔍 Ampliar</div>
                    </div>
                  </td>
                  <td>{{ reg.FechaRegistro | date:'dd/MM/yyyy HH:mm' }}</td>
                  <td>
                    <span class="status-badge" [ngClass]="getStatusClass(reg.Estatus)">
                      {{ getStatusText(reg.Estatus) }}
                    </span>
                  </td>
                  <td>
                    <div class="recharge-cell" *ngIf="reg.Estatus === 4 || reg.Estatus === 5">
                      <span class="carrier" *ngIf="reg.FolioRecarga">Folio: {{ reg.FolioRecarga }}</span>
                      <span class="phone" *ngIf="reg.TelefonoRecarga">A: {{ reg.TelefonoRecarga }}</span>
                      <span class="pending-lbl" *ngIf="reg.Estatus === 5">Procesando...</span>
                    </div>
                    <span class="no-info" *ngIf="reg.Estatus !== 4 && reg.Estatus !== 5">—</span>
                  </td>
                  <td>
                    <div class="action-buttons" *ngIf="reg.Estatus === 1">
                      <button (click)="openApprovalModal(reg)" class="btn-action approve-btn" title="Aprobar Registro">
                        ✓ Aprobar
                      </button>
                      <button (click)="openRejectionModal(reg)" class="btn-action reject-btn" title="Rechazar Registro">
                        ✕ Rechazar
                      </button>
                    </div>
                    <div class="action-history" *ngIf="reg.Estatus !== 1">
                      <span class="rejection-reason" *ngIf="reg.Estatus === 3" [title]="reg.MotivoRechazo || ''">
                        Motivo: {{ reg.MotivoRechazo || 'Sin especificar' }}
                      </span>
                      <span class="processed-ok" *ngIf="reg.Estatus === 2 || reg.Estatus === 4 || reg.Estatus === 5">
                        Procesado
                      </span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
            <ng-template #noData>
              <div class="empty-state">
                <span class="empty-icon">📁</span>
                <p>No se encontraron registros en la base de datos.</p>
              </div>
            </ng-template>
          </div>
        </section>
      </main>

      <!-- Lightbox Image Modal -->
      <div class="lightbox" *ngIf="activeLightboxImage()" (click)="closeLightbox()">
        <div class="lightbox-content" (click)="$event.stopPropagation()">
          <img [src]="activeLightboxImage()" alt="Lightbox Image">
          <button class="close-lightbox" (click)="closeLightbox()">✕</button>
        </div>
      </div>

      <!-- Rejection Form Modal Dialog -->
      <div class="rejection-modal" *ngIf="rejectionTarget()">
        <div class="modal-card">
          <h3>Rechazar Registro</h3>
          <p>Por favor, especifica el motivo del rechazo para notificar al participante:</p>
          
          <div class="rejection-options">
            <button *ngFor="let opt of commonRejections" (click)="rejectionReason = opt" class="reason-opt-btn" [class.selected]="rejectionReason === opt">
              {{ opt }}
            </button>
          </div>

          <textarea 
            [(ngModel)]="rejectionReason" 
            placeholder="Escribe un motivo personalizado..." 
            rows="3">
          </textarea>

          <div class="modal-footer">
            <button (click)="closeRejectionModal()" class="modal-btn cancel-btn">Cancelar</button>
            <button 
              (click)="submitRejection()" 
              class="modal-btn confirm-btn" 
              [disabled]="!rejectionReason.trim() || processingRejection()">
              {{ processingRejection() ? 'Enviando...' : 'Rechazar y Notificar' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Approval Form Modal Dialog -->
      <div class="rejection-modal" *ngIf="approvalTarget()">
        <div class="modal-card">
          <h3>Aprobar Registro y Realizar Recarga</h3>
          <p>Confirma o introduce el número telefónico de 10 dígitos para realizar la recarga de tiempo aire:</p>
          
          <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-size: 0.85rem; color: #aeaeb2;">Número Telefónico</label>
            <input 
              type="text" 
              [(ngModel)]="approvalPhone" 
              placeholder="Ej: 5512345678" 
              maxLength="10"
              style="width: 100%; padding: 12px; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; color: #fff; font-size: 1rem; outline: none; box-sizing: border-box;">
          </div>

          <div class="modal-footer">
            <button (click)="closeApprovalModal()" class="modal-btn cancel-btn">Cancelar</button>
            <button 
              (click)="submitApproval()" 
              class="modal-btn confirm-btn" 
              style="background-color: #00A753;"
              [disabled]="!approvalPhone.trim() || processingApproval()">
              {{ processingApproval() ? 'Procesando...' : 'Aprobar y Recargar' }}
            </button>
          </div>
        </div>
      </div>
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
    .code-badge {
      background: rgba(255, 209, 0, 0.05);
      border: 1px solid rgba(255, 209, 0, 0.2);
      padding: 6px 12px;
      border-radius: 6px;
      font-family: monospace;
      color: #FFD100;
      font-weight: 600;
      font-size: 0.95rem;
    }
    .image-preview {
      position: relative;
      width: 80px;
      height: 60px;
      border-radius: 8px;
      overflow: hidden;
      cursor: pointer;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .image-preview img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s;
    }
    .image-preview:hover img {
      transform: scale(1.1);
    }
    .hover-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.6);
      display: flex;
      justify-content: center;
      align-items: center;
      font-size: 0.75rem;
      color: #fff;
      opacity: 0;
      transition: opacity 0.3s;
    }
    .image-preview:hover .hover-overlay {
      opacity: 1;
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
    
    .recharge-cell {
      display: flex;
      flex-direction: column;
      font-size: 0.8rem;
    }
    .recharge-cell .carrier { font-weight: 600; color: #fff; }
    .recharge-cell .phone { color: #aeaeb2; }
    .recharge-cell .pending-lbl { color: #009BE0; font-style: italic; }
    .no-info { color: #48484a; }
 
    .action-buttons {
      display: flex;
      gap: 10px;
    }
    .btn-action {
      padding: 8px 12px;
      border-radius: 8px;
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
      border: none;
      transition: background 0.3s;
    }
    .approve-btn {
      background-color: #00A753;
      color: #fff;
    }
    .approve-btn:hover { background-color: #008240; }
    .reject-btn {
      background-color: #E31B23;
      color: #fff;
    }
    .reject-btn:hover { background-color: #c4141c; }
    
    .action-history {
      font-size: 0.8rem;
    }
    .rejection-reason {;
    }
    .rejection-reason {
      color: #ff453a;
      display: block;
      max-width: 150px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      cursor: help;
    }
    .processed-ok {
      color: #8e8e93;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #8e8e93;
    }
    .empty-icon { font-size: 3rem; margin-bottom: 12px; display: block; }
    
    /* Lightbox */
    .lightbox {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.9);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 1000;
      animation: fadeIn 0.3s ease;
    }
    .lightbox-content {
      position: relative;
      max-width: 90%;
      max-height: 90%;
    }
    .lightbox-content img {
      max-width: 100%;
      max-height: 80vh;
      border-radius: 12px;
      border: 2px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.8);
    }
    .close-lightbox {
      position: absolute;
      top: -40px;
      right: 0;
      background: transparent;
      border: none;
      color: #fff;
      font-size: 2rem;
      cursor: pointer;
    }

    /* Modal */
    .rejection-modal {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.7);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 1000;
      padding: 20px;
    }
    .modal-card {
      width: 100%;
      max-width: 500px;
      background-color: #1c1c1e;
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }
    .modal-card h3 { margin: 0 0 10px 0; color: #fff; }
    .modal-card p { font-size: 0.9rem; color: #aeaeb2; margin-bottom: 20px; }
    .rejection-options {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 20px;
    }
    .reason-opt-btn {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.08);
      color: #fff;
      padding: 8px 12px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 0.8rem;
      transition: all 0.3s;
    }
    .reason-opt-btn:hover { background: rgba(255, 255, 255, 0.1); }
    .reason-opt-btn.selected {
      background: rgba(255, 69, 58, 0.15);
      border-color: #ff453a;
      color: #ff453a;
    }
    textarea {
      width: 100%;
      background: rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 10px;
      padding: 12px;
      color: #fff;
      font-size: 0.9rem;
      outline: none;
      resize: none;
      margin-bottom: 24px;
    }
    textarea:focus { border-color: #ff453a; }
    .modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
    }
    .modal-btn {
      padding: 10px 16px;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      border: none;
    }
    .cancel-btn { background: rgba(255, 255, 255, 0.05); color: #fff; }
    .cancel-btn:hover { background: rgba(255, 255, 255, 0.1); }
    .confirm-btn { background-color: #ff453a; color: #fff; }
    .confirm-btn:hover:not(:disabled) { background-color: #c9342b; }
    .confirm-btn:disabled { opacity: 0.5; cursor: not-allowed; }
  `]
})
export class AdminRegistrosComponent implements OnInit {
  private api = inject(ApiService);
  private router = inject(Router);

  registros = signal<Registro[]>([]);
  pendingRegistros = computed(() => this.registros().filter(r => r.Estatus === 1));
  loading = signal(false);
  
  stats = signal({
    total: 0,
    pending: 0,
    approved: 0,
    rejected: 0
  });

  activeLightboxImage = signal<string | null>(null);
  
  // Rejection logic
  rejectionTarget = signal<Registro | null>(null);
  rejectionReason = '';
  processingRejection = signal(false);

  // Approval logic
  approvalTarget = signal<Registro | null>(null);
  approvalPhone = '';
  processingApproval = signal(false);

  commonRejections = [
    'Foto borrosa / no legible',
    'Falta código escrito en las cajetillas',
    'No se aprecian las 3 cajetillas juntas',
    'Las cajas ya fueron registradas previamente',
    'Código de participación incorrecto'
  ];

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.loading.set(true);
    this.api.getRegistros().subscribe({
      next: (res: any) => {
        if (res.success) {
          const list = res.data as Registro[];
          this.registros.set(list);
          this.calculateStats(list);
        }
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  calculateStats(list: Registro[]) {
    const total = list.length;
    const pending = list.filter(r => r.Estatus === 1).length;
    const approved = list.filter(r => r.Estatus === 2 || r.Estatus === 4 || r.Estatus === 5).length;
    const rejected = list.filter(r => r.Estatus === 3).length;
    this.stats.set({ total, pending, approved, rejected });
  }

  getStatusText(status: number): string {
    switch (status) {
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
      case 1: return 'pending';
      case 2: return 'approved';
      case 3: return 'rejected';
      case 4: return 'completed';
      case 5: return 'process';
      default: return '';
    }
  }

  openApprovalModal(reg: Registro) {
    this.approvalTarget.set(reg);
    const rawPhone = reg.TelefonoRecarga || reg.Celular || '';
    this.approvalPhone = rawPhone.replace(/\D/g, '').slice(-10);
  }

  closeApprovalModal() {
    this.approvalTarget.set(null);
    this.approvalPhone = '';
  }

  submitApproval() {
    const reg = this.approvalTarget();
    if (!reg || !this.approvalPhone.trim()) return;

    if (this.approvalPhone.length !== 10 || isNaN(Number(this.approvalPhone))) {
      alert('Por favor introduce un número de teléfono válido de 10 dígitos.');
      return;
    }

    this.processingApproval.set(true);
    this.api.aprobarRegistro(reg.idRegistro, this.approvalPhone).subscribe({
      next: (res: any) => {
        if (res.success) {
          this.loadData();
          this.closeApprovalModal();
        }
        this.processingApproval.set(false);
      },
      error: (err) => {
        alert(err?.error?.error || 'Error al aprobar registro.');
        this.processingApproval.set(false);
      }
    });
  }

  openRejectionModal(reg: Registro) {
    this.rejectionTarget.set(reg);
    this.rejectionReason = '';
  }

  closeRejectionModal() {
    this.rejectionTarget.set(null);
    this.rejectionReason = '';
  }

  submitRejection() {
    const reg = this.rejectionTarget();
    if (!reg || !this.rejectionReason.trim()) return;

    this.processingRejection.set(true);
    this.api.rechazarRegistro(reg.idRegistro, this.rejectionReason).subscribe({
      next: (res: any) => {
        if (res.success) {
          this.loadData();
          this.closeRejectionModal();
        }
        this.processingRejection.set(false);
      },
      error: (err) => {
        alert(err?.error?.error || 'Error al rechazar registro.');
        this.processingRejection.set(false);
      }
    });
  }

  openLightbox(url: string) {
    this.activeLightboxImage.set(url);
  }

  closeLightbox() {
    this.activeLightboxImage.set(null);
  }

  logout() {
    this.api.logout();
    this.router.navigate(['/admin/login']);
  }
}
