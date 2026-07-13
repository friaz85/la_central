import { Component, inject, OnInit, signal, computed, HostListener } from '@angular/core';
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
          <img src="/logo.png" alt="Logo" style="width: 38px; height: 38px; border-radius: 8px; object-fit: cover; box-shadow: 0 2px 8px rgba(255, 102, 0, 0.25);">
          <div class="logo-text">
            <h2>Gatorade G15K</h2>
            <p>Panel de Administración</p>
          </div>
          <nav class="header-nav" style="display: flex; gap: 20px; margin-left: 40px;">
            <a routerLink="/admin/dashboard" routerLinkActive="active" style="color: #8e8e93; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: color 0.3s;" onmouseenter="this.style.color='#fff'" onmouseleave="this.style.color='#8e8e93'">Dashboard</a>
            <a routerLink="/admin/registros" routerLinkActive="active" style="color: #ff453a; text-decoration: none; font-size: 0.9rem; font-weight: 600;">Registros</a>

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
                  <th>Foto Evidencia</th>
                  <th>Fecha Registro</th>
                  <th>Estatus</th>
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
                    <div class="action-buttons" *ngIf="reg.Estatus === 1">
                      <button (click)="openValidationModal(reg)" class="btn-action approve-btn" style="background-color: #FF6600; color: #ffffff;" title="Validar Registro">
                        ✓ Validar
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

      <!-- Validation Form Modal Dialog -->
      <div class="rejection-modal" *ngIf="showModal()" (click)="closeModal()">
        <div class="modal-card modal-xl" (click)="$event.stopPropagation()" style="background-color: #1A1A1A; border: 1px solid rgba(255, 102, 0, 0.3); max-width: 95vw; width: 95vw; box-sizing: border-box;">
          <div class="modal-header-val" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255, 102, 0, 0.2); padding-bottom: 15px; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #fff; display: flex; align-items: center; gap: 8px;">
              🔍 Validar Ticket de Compra
            </h3>
            <button (click)="closeModal()" style="background: none; border: none; color: #aeaeb2; font-size: 1.5rem; cursor: pointer;">✕</button>
          </div>

          <div class="ticket-validator-layout" style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; max-height: 65vh; overflow-y: auto; padding-right: 8px;">
            <!-- Imagen del ticket -->
            <div class="ticket-col" style="background: rgba(0, 0, 0, 0.2); border-radius: 12px; padding: 15px; border: 1px solid rgba(255, 102, 0, 0.1); display: flex; justify-content: center; align-items: center; min-height: 350px;">
              <img [src]="ticketImg()" alt="Ticket de compra" style="max-width: 100%; max-height: 480px; object-fit: contain; border-radius: 8px;">
            </div>

            <!-- Formulario de validación -->
            <div class="ticket-form" style="display: flex; flex-direction: column; gap: 16px;">
              <div class="ticket-info-header" style="background: rgba(255, 102, 0, 0.05); padding: 15px; border-radius: 12px; border: 1px solid rgba(255, 102, 0, 0.2);">
                <div style="font-size: 0.8rem; color: #aeaeb2; text-transform: uppercase;">Registro #{{ currentReg()?.idRegistro }}</div>
                <div style="font-size: 1.1rem; font-weight: bold; color: #fff; margin-top: 4px;">{{ currentReg()?.NombreUsuario || 'Participante' }}</div>
                <div style="font-size: 0.9rem; color: #FF6600; font-family: monospace; margin-top: 2px;">📞 {{ currentReg()?.Celular }}</div>
              </div>

              <!-- Selector de Acción -->
              <div>
                <label style="display: block; margin-bottom: 8px; font-size: 0.85rem; color: #aeaeb2; font-weight: 600;">Acción</label>
                <div style="display: flex; gap: 12px;">
                  <button type="button" (click)="accion = 'aprobar'" [style.background-color]="accion === 'aprobar' ? '#00824A' : 'rgba(255, 255, 255, 0.05)'" style="flex: 1; padding: 12px; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: #fff; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                    ✓ Aprobar
                  </button>
                  <button type="button" (click)="accion = 'rechazar'" [style.background-color]="accion === 'rechazar' ? '#E31B23' : 'rgba(255, 255, 255, 0.05)'" style="flex: 1; padding: 12px; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: #fff; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                    ✕ Rechazar
                  </button>
                </div>
              </div>

              <!-- Campos de Aprobación -->
              <div *ngIf="accion === 'aprobar'" style="display: flex; flex-direction: column; gap: 12px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                  <div>
                    <label style="display: block; margin-bottom: 6px; font-size: 0.85rem; color: #aeaeb2;">Folio Ticket *</label>
                    <input type="text" [(ngModel)]="form.folio" (blur)="onFolioBlur()" placeholder="Ej: T-12345" style="width: 100%; padding: 10px; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; outline: none; box-sizing: border-box;">
                    <div *ngIf="folioDuplicado()" style="color: #ff453a; font-size: 0.75rem; margin-top: 4px; font-weight: 600;">
                      ⚠️ Este folio ya existe en un registro aprobado.
                    </div>
                  </div>
                  <div>
                    <label style="display: block; margin-bottom: 6px; font-size: 0.85rem; color: #aeaeb2;">Fecha Ticket *</label>
                    <input type="date" [(ngModel)]="form.fecha" style="width: 100%; padding: 10px; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; outline: none; box-sizing: border-box;">
                  </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                  <div>
                    <label style="display: block; margin-bottom: 6px; font-size: 0.85rem; color: #aeaeb2;">Monto Ticket ($) *</label>
                    <input type="number" [(ngModel)]="form.monto" placeholder="0.00" step="0.01" min="0" style="width: 100%; padding: 10px; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; outline: none; box-sizing: border-box;">
                  </div>
                  <div>
                    <label style="display: block; margin-bottom: 6px; font-size: 0.85rem; color: #aeaeb2;">Cadena *</label>
                    <select [ngModel]="form.cadena" (ngModelChange)="onCadenaChange($event)" style="width: 100%; padding: 10px; background: #1A1A1A; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; outline: none; box-sizing: border-box; cursor: pointer;">
                      <option value="">Selecciona...</option>
                      <option *ngFor="let c of catalogs.cadenas" [value]="c.idCadena">{{ c.Nombre }}</option>
                    </select>
                  </div>
                </div>

                <!-- Selector de Sucursal (Buscador) -->
                <div *ngIf="sucursalesCargadas().length > 0" style="position: relative;" id="sucursal-search-dropdown-container">
                  <label style="display: block; margin-bottom: 6px; font-size: 0.85rem; color: #aeaeb2; font-weight: 600;">Sucursal / Tienda *</label>
                  
                  <div (click)="toggleSucursalDropdown($event)"
                       style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; min-height: 38px; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 0 12px; color: #fff; box-sizing: border-box;">
                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.9rem;">
                      {{ getSelectedSucursalName() }}
                    </span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink: 0; color: #aeaeb2; margin-left: 8px;">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </div>

                  <div *ngIf="showSucursalDropdown" 
                       style="position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #1C1C1E; border: 1px solid rgba(255, 102, 0, 0.3); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); z-index: 1000; padding: 8px; display: flex; flex-direction: column; gap: 8px;">
                    <div style="position: relative; display: flex; align-items: center;">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="position: absolute; left: 10px; width: 14px; height: 14px; color: #aeaeb2; pointer-events: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                      </svg>
                      <input type="text" 
                             style="width: 100%; padding: 8px 10px 8px 32px; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; color: #fff; font-size: 0.85rem; outline: none; box-sizing: border-box;"
                             placeholder="Buscar sucursal por nombre o número..." 
                             [(ngModel)]="searchSucursalText"
                             (click)="$event.stopPropagation()">
                    </div>

                    <div style="max-height: 200px; overflow-y: auto; display: flex; flex-direction: column; gap: 2px;">
                      <div (click)="selectSucursal(null)" 
                           style="padding: 8px 10px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; transition: background 0.2s;"
                           [style.background]="!form.sucursal ? 'rgba(255, 102, 0, 0.15)' : 'transparent'"
                           class="dropdown-item-val">
                        Selecciona...
                      </div>
                      <div *ngFor="let s of filteredSucursales()" 
                           (click)="selectSucursal(s)"
                           style="padding: 8px 10px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; display: flex; flex-direction: column; gap: 2px; transition: background 0.2s;"
                           [style.background]="form.sucursal == s.idSucursal ? 'rgba(255, 102, 0, 0.15)' : 'transparent'"
                           class="dropdown-item-val">
                        <span style="font-weight: 500; color: #fff;">{{ s.Tienda }}</span>
                        <span style="font-size: 0.75rem; color: #aeaeb2; font-family: monospace;">Número: {{ s.NumeroTienda }}</span>
                      </div>
                      <div *ngIf="!filteredSucursales().length" 
                           style="padding: 12px; text-align: center; font-size: 0.8rem; color: #aeaeb2;">
                        Sin resultados
                      </div>
                    </div>
                  </div>
                  <style>
                    .dropdown-item-val:hover {
                      background-color: rgba(255, 102, 0, 0.1) !important;
                    }
                  </style>
                </div>

                <div>
                  <label style="display: block; margin-bottom: 6px; font-size: 0.85rem; color: #aeaeb2;">Producto *</label>
                  <select [(ngModel)]="form.producto" style="width: 100%; padding: 10px; background: #1A1A1A; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; outline: none; box-sizing: border-box; cursor: pointer;">
                    <option value="">Selecciona...</option>
                    <option *ngFor="let p of catalogs.productos" [value]="p.idProducto">{{ p.Producto }} (SKU: {{ p.SKU || 'N/A' }})</option>
                  </select>
                </div>
              </div>

              <!-- Campos de Rechazo -->
              <div *ngIf="accion === 'rechazar'" style="display: flex; flex-direction: column; gap: 12px;">
                <label style="display: block; margin-bottom: 6px; font-size: 0.85rem; color: #aeaeb2;">Motivo de Rechazo *</label>
                
                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
                  <button type="button" *ngFor="let opt of commonRejections" (click)="form.motivo = opt" [style.background]="form.motivo === opt ? 'rgba(227, 27, 35, 0.2)' : 'rgba(255,255,255,0.05)'" [style.border-color]="form.motivo === opt ? '#ff453a' : 'rgba(255,255,255,0.1)'" style="color: #fff; padding: 6px 10px; border-radius: 6px; border: 1px solid; cursor: pointer; font-size: 0.75rem; transition: all 0.3s;">
                    {{ opt }}
                  </button>
                </div>

                <textarea [(ngModel)]="form.motivo" placeholder="Escribe un motivo personalizado..." rows="3" style="width: 100%; padding: 10px; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; outline: none; box-sizing: border-box; resize: none;"></textarea>
              </div>
            </div>
          </div>

          <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid rgba(255, 102, 0, 0.2); padding-top: 15px; margin-top: 20px;">
            <button (click)="closeModal()" class="modal-btn cancel-btn">Cancelar</button>
            <button (click)="saveValidation()" class="modal-btn confirm-btn" [style.background-color]="accion === 'aprobar' ? '#00824A' : '#ff453a'" [disabled]="saving()">
              {{ saving() ? 'Guardando...' : 'Guardar Validación' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .dashboard-layout {
      min-height: 100vh;
      background-color: #0d0d0d;
      color: #f5f5f5;
      font-family: 'Inter', sans-serif;
    }
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 40px;
      background-color: #1a1a1a;
      border-bottom: 1px solid rgba(255, 102, 0, 0.2);
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
      background: rgba(255, 102, 0, 0.15);
      color: #FF6600;
      padding: 4px 10px;
      border-radius: 20px;
      font-weight: 600;
      border: 1px solid rgba(255, 102, 0, 0.25);
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
      background-color: #1a1a1a;
      border: 1px solid rgba(255, 102, 0, 0.1);
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
    .stat-card.pending { border-left: 4px solid #FF6600; }
    .stat-card.approved { border-left: 4px solid #00824A; }
    .stat-card.rejected { border-left: 4px solid #E31B23; }
    
    .table-card {
      background-color: #1a1a1a;
      border-radius: 20px;
      border: 1px solid rgba(255, 102, 0, 0.1);
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
      border-bottom: 1px solid rgba(255, 102, 0, 0.2);
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
      background: rgba(255, 102, 0, 0.05);
      border: 1px solid rgba(255, 102, 0, 0.2);
      padding: 6px 12px;
      border-radius: 6px;
      font-family: monospace;
      color: #FF6600;
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
    .status-badge.pending { background: rgba(255, 102, 0, 0.15); color: #FF6600; }
    .status-badge.approved { background: rgba(0, 130, 74, 0.15); color: #00824A; }
    .status-badge.rejected { background: rgba(227, 27, 35, 0.15); color: #E31B23; }
    .status-badge.completed { background: rgba(0, 130, 74, 0.15); color: #00824A; }
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

  // Validation modal state
  showModal = signal(false);
  currentReg = signal<Registro | null>(null);
  ticketImg = signal('');
  saving = signal(false);
  accion = 'aprobar';
  folioDuplicado = signal(false);

  searchSucursalText = '';
  showSucursalDropdown = false;
  sucursalesCargadas = signal<any[]>([]);

  form = {
    folio: '',
    fecha: '',
    monto: '',
    cadena: '',
    producto: '',
    sucursal: '',
    motivo: ''
  };

  catalogs: any = { cadenas: [], productos: [], sucursales: [] };

  @HostListener('document:click', ['$event'])
  onClickOutside(event: Event) {
    const target = event.target as HTMLElement;
    if (!target.closest('#sucursal-search-dropdown-container')) {
      this.showSucursalDropdown = false;
    }
  }

  onCadenaChange(chainId: any) {
    this.form.cadena = chainId;
    this.form.sucursal = '';
    this.searchSucursalText = '';
    this.showSucursalDropdown = false;
    this.sucursalesCargadas.set([]);

    if (!chainId) return;

    const chain = this.catalogs.cadenas.find((c: any) => c.idCadena == chainId);
    const nombre = (chain?.Nombre || '').toLowerCase();
    if (nombre.includes('oxxo') || nombre.includes('seven') || nombre.includes('7-eleven') || nombre.includes('7')) {
      this.api.getSucursales(chainId).subscribe({
        next: (res: any) => {
          if (res.success) {
            this.sucursalesCargadas.set(res.data || []);
          }
        }
      });
    }
  }

  filteredSucursales() {
    const query = this.searchSucursalText.toLowerCase().trim();
    const list = this.sucursalesCargadas();
    if (!query) {
      return list;
    }
    return list.filter((s: any) => {
      const tienda = (s.Tienda || '').toLowerCase();
      const num = (s.NumeroTienda || '').toLowerCase();
      return tienda.includes(query) || num.includes(query);
    });
  }

  getSelectedSucursalName() {
    const selectedId = this.form.sucursal;
    if (!selectedId) return 'Selecciona...';
    const suc = this.sucursalesCargadas().find((s: any) => s.idSucursal == selectedId);
    return suc ? `${suc.Tienda} (#${suc.NumeroTienda})` : 'Selecciona...';
  }

  toggleSucursalDropdown(event: Event) {
    event.stopPropagation();
    this.showSucursalDropdown = !this.showSucursalDropdown;
  }

  selectSucursal(s: any) {
    this.form.sucursal = s ? s.idSucursal : '';
    this.showSucursalDropdown = false;
    this.searchSucursalText = '';
  }

  commonRejections = [
    'Foto borrosa / no legible',
    'No se aprecian los productos participantes',
    'Las cajas o ticket ya fueron registrados previamente',
    'Monto de compra no cumple con el mínimo ($95.00 MXN)',
    'Ticket de compra alterado o no válido'
  ];

  ngOnInit() {
    this.loadCatalogs();
    this.loadData();
  }

  async loadCatalogs() {
    this.api.getCadenas().subscribe(d => this.catalogs.cadenas = d.data || d || []);
    this.api.getProductos().subscribe(d => this.catalogs.productos = d.data || d || []);
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

  openValidationModal(reg: Registro) {
    this.showModal.set(true);
    this.currentReg.set(reg);
    this.accion = 'aprobar';
    this.ticketImg.set(reg.FotoCajasUrl);
    this.folioDuplicado.set(false);
    this.searchSucursalText = '';
    this.showSucursalDropdown = false;
    this.sucursalesCargadas.set([]);
    this.form = {
      folio: '',
      fecha: '',
      monto: '',
      cadena: '',
      producto: '',
      sucursal: '',
      motivo: ''
    };
  }

  closeModal() {
    this.showModal.set(false);
    this.currentReg.set(null);
  }

  onFolioBlur() {
    const folio = this.form.folio.trim();
    const reg = this.currentReg();
    if (!folio || !reg) {
      this.folioDuplicado.set(false);
      return;
    }

    this.api.checkFolio(folio, reg.idRegistro).subscribe({
      next: (res: any) => {
        if (res.success) {
          this.folioDuplicado.set(res.exists);
        }
      },
      error: () => this.folioDuplicado.set(false)
    });
  }

  saveValidation() {
    const reg = this.currentReg();
    if (!reg) return;

    if (this.accion === 'aprobar') {
      if (!this.form.folio.trim()) { alert('El Folio del Ticket es requerido'); return; }
      if (this.folioDuplicado()) { alert('Este folio ya existe en otro registro aprobado.'); return; }
      if (!this.form.fecha) { alert('La Fecha del Ticket es requerida'); return; }
      if (this.form.monto === '') { alert('El Monto del Ticket es requerido'); return; }
      if (Number(this.form.monto) < 0) { alert('El Monto del Ticket no puede ser negativo'); return; }
      if (!this.form.cadena) { alert('La Cadena es requerida'); return; }
      if (this.sucursalesCargadas().length > 0 && !this.form.sucursal) { alert('La Sucursal / Tienda es requerida'); return; }
      if (!this.form.producto) { alert('El Producto es requerido'); return; }
    } else {
      if (!this.form.motivo.trim()) { alert('Selecciona o escribe el motivo de rechazo'); return; }
    }

    this.saving.set(true);

    if (this.accion === 'aprobar') {
      const payload = {
        FolioTicket: this.form.folio,
        FechaTicket: this.form.fecha,
        MontoTicket: this.form.monto,
        idCadena: this.form.cadena,
        idProducto: this.form.producto,
        idSucursal: this.form.sucursal || null
      };
      this.api.aprobarRegistro(reg.idRegistro, payload).subscribe({
        next: (res: any) => {
          this.saving.set(false);
          this.closeModal();
          this.loadData();
        },
        error: (err) => {
          alert(err?.error?.error || 'Error al aprobar el registro.');
          this.saving.set(false);
        }
      });
    } else {
      this.api.rechazarRegistro(reg.idRegistro, this.form.motivo).subscribe({
        next: (res: any) => {
          this.saving.set(false);
          this.closeModal();
          this.loadData();
        },
        error: (err) => {
          alert(err?.error?.error || 'Error al rechazar el registro.');
          this.saving.set(false);
        }
      });
    }
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
