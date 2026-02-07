// Simulación de base de datos (en memoria para este ejemplo)
const users = [
  { id: 1, username: 'admin', password: 'admin123', role: 'admin' },
  { id: 2, username: 'user1', password: 'user123', role: 'user' }
];

const attendanceRecords = [];

// Función para redondear la hora según las reglas especificadas
function roundTime(timestamp, type) {
  const date = new Date(timestamp);
  const minutes = date.getMinutes();
  let roundedMinutes;

  if (type === 'entry') {
    // Redondear al siguiente cuarto de hora
    if (minutes < 15) roundedMinutes = 15;
    else if (minutes < 30) roundedMinutes = 30;
    else if (minutes < 45) roundedMinutes = 45;
    else {
      roundedMinutes = 0;
      date.setHours(date.getHours() + 1);
    }
  } else if (type === 'exit') {
    // Redondear al cuarto de hora anterior
    if (minutes <= 15) roundedMinutes = 0;
    else if (minutes <= 30) roundedMinutes = 15;
    else if (minutes <= 45) roundedMinutes = 30;
    else roundedMinutes = 45;
  }

  date.setMinutes(roundedMinutes);
  date.setSeconds(0);
  date.setMilliseconds(0);
  return date;
}

// Función para registrar asistencia
function registerAttendance(userId, location, type, timestamp) {
  const roundedTime = roundTime(timestamp, type);
  const record = {
    userId,
    location,
    type,
    timestamp: roundedTime,
    originalTimestamp: new Date(timestamp)
  };
  attendanceRecords.push(record);
  return record;
}

// Función para obtener registros de asistencia
function getAttendanceRecords(userId, role) {
  if (role === 'admin') {
    return attendanceRecords;
  } else {
    return attendanceRecords.filter(record => record.userId === userId);
  }
}

// Función para ajustar hora manualmente (solo admin)
function adjustAttendanceRecord(recordId, newTimestamp, adminId) {
  const record = attendanceRecords.find(r => r.id === recordId);
  if (!record) throw new Error('Registro no encontrado');
  record.timestamp = new Date(newTimestamp);
  record.adjustedBy = adminId;
  return record;
}

// Autenticación de usuario
function authenticate(username, password) {
  const user = users.find(u => u.username === username && u.password === password);
  if (!user) throw new Error('Credenciales inválidas');
  return user;
}

// Simulación de escaneo de QR
function scanQR(qrData) {
  // Suponemos que qrData contiene la ubicación codificada
  return qrData; // En un sistema real, aquí se decodificaría el QR
}

// Ejemplo de uso
try {
  // Autenticar usuario
  const user = authenticate('user1', 'user123');
  
  // Escanear QR y registrar entrada
  const location = scanQR('location:office1');
  const entryRecord = registerAttendance(user.id, location, 'entry', new Date());
  console.log('Entrada registrada:', entryRecord);

  // Registrar salida
  const exitRecord = registerAttendance(user.id, location, 'exit', new Date());
  console.log('Salida registrada:', exitRecord);

  // Obtener registros (usuario solo ve los suyos)
  const userRecords = getAttendanceRecords(user.id, user.role);
  console.log('Registros del usuario:', userRecords);

  // Admin ajusta un registro
  const admin = authenticate('admin', 'admin123');
  const adjustedRecord = adjustAttendanceRecord(entryRecord.id, new Date(), admin.id);
  console.log('Registro ajustado:', adjustedRecord);

  // Admin ve todos los registros
  const allRecords = getAttendanceRecords(admin.id, admin.role);
  console.log('Todos los registros:', allRecords);
} catch (error) {
  console.error('Error:', error.message);
}