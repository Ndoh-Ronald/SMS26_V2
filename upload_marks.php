<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Offline Mark Entry | Synergia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Setup IndexedDB
        let db;
        const request = indexedDB.open("SynergiaMarksDB", 1);

        request.onupgradeneeded = function(event) {
            db = event.target.result;
            db.createObjectStore("marks", { keyPath: "id", autoIncrement: true });
        };
        request.onsuccess = event => db = event.target.result;

        const saveMarkOffline = async data => {
            const tx = db.transaction("marks", "readwrite");
            tx.objectStore("marks").add(data);
        };
        const getOfflineMarks = () => new Promise(res => {
            const tx = db.transaction("marks", "readonly");
            const req = tx.objectStore("marks").getAll();
            req.onsuccess = () => res(req.result || []);
        });
        const clearSyncedMarks = async () => {
            const tx = db.transaction("marks", "readwrite");
            tx.objectStore("marks").clear();
        };

        const syncMarks = async () => {
            if (!navigator.onLine) return;
            const marks = await getOfflineMarks();
            if (!marks.length) return;
            const res = await fetch('upload_marks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(marks)
            });
            if (res.ok) {
                await clearSyncedMarks();
                alert('Marks synced successfully!');
            }
        };
        window.addEventListener('online', syncMarks);
    </script>
</head>
<body class="min-h-screen bg-gray-50 py-10 px-4">
    <div class="max-w-xl mx-auto bg-white p-6 rounded-lg shadow">
        <h2 class="text-2xl font-bold text-center text-indigo-600 mb-6">Enter Student Marks</h2>

        <form id="markForm" class="space-y-4">
            <input type="text" name="student_name" placeholder="Student Name" required class="w-full p-2 border rounded">
            <input type="text" name="subject_id" placeholder="Subject ID" required class="w-full p-2 border rounded">
            <input type="number" name="score" placeholder="Score" step="0.01" required class="w-full p-2 border rounded">
            <input type="text" name="term" placeholder="Term (e.g., 1st Term)" required class="w-full p-2 border rounded">
            <input type="text" name="session" placeholder="Session Year (e.g., 2024/2025)" required class="w-full p-2 border rounded">
            <button type="submit" class="w-full bg-indigo-600 text-white p-2 rounded hover:bg-indigo-700">Save Mark</button>
        </form>

        <p class="text-sm text-gray-500 mt-4 text-center">Offline entries will sync automatically when you're back online.</p>
    </div>

    <script>
        document.getElementById('markForm').addEventListener('submit', async e => {
            e.preventDefault();
            const f = e.target;
            const markData = {
                student_name: f.student_name.value.trim(),
                subject_id: f.subject_id.value.trim(),
                score: parseFloat(f.score.value),
                term: f.term.value.trim(),
                session: f.session.value.trim(),
                timestamp: new Date().toISOString()
            };
            await saveMarkOffline(markData);
            alert('Mark saved locally. Will sync when online.');
            f.reset();
        });
    </script>
</body>
</html>
