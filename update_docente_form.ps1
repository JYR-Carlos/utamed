$file = "c:\Users\yampa\Trabajos\UTA\edit\utamed\utamed\resources\js\pages\admin\Usuarios.svelte"
$content = Get-Content $file -Raw

# Reemplazo para el formulario de docente
$oldPattern = @'
		<div class="form-group">
			<label for="nombre_completo" class="form-label">Nombre Completo \*</label>
			<input
				id="nombre_completo"
				type="text"
				bind:value=\{docenteFormData\.nombre_completo\}
				class="form-input"
				placeholder="Ej: Dr\. Juan Pérez García"
				required
			/>
		</div>
'@

$newPattern = @'
		<div class="form-row">
			<div class="form-group">
				<label for="nombre1_docente" class="form-label">Primer Nombre *</label>
				<input
					id="nombre1_docente"
					type="text"
					bind:value={docenteFormData.nombre1}
					class="form-input"
					placeholder="Ej: Juan"
					required
				/>
			</div>

			<div class="form-group">
				<label for="nombre2_docente" class="form-label">Segundo Nombre</label>
				<input
					id="nombre2_docente"
					type="text"
					bind:value={docenteFormData.nombre2}
					class="form-input"
					placeholder="Ej: Carlos"
				/>
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="apellido1_docente" class="form-label">Primer Apellido *</label>
				<input
					id="apellido1_docente"
					type="text"
					bind:value={docenteFormData.apellido1}
					class="form-input"
					placeholder="Ej: González"
					required
				/>
			</div>

			<div class="form-group">
				<label for="apellido2_docente" class="form-label">Segundo Apellido</label>
				<input
					id="apellido2_docente"
					type="text"
					bind:value={docenteFormData.apellido2}
					class="form-input"
					placeholder="Ej: Pérez"
				/>
			</div>
		</div>

		<div class="form-group">
			<label for="email_docente" class="form-label">Email</label>
			<input
				id="email_docente"
				type="email"
				bind:value={docenteFormData.email}
				class="form-input"
				placeholder="Ej: juan.gonzalez@ejemplo.com"
			/>
		</div>
'@

# Hacer el reemplazo usando regex
$content = $content -replace [regex]::Escape($oldPattern), $newPattern

# Guardar el archivo
Set-Content $file -Value $content -NoNewline

Write-Host "Archivo actualizado exitosamente"
