//
//  TeacherSelectionView.swift
//  imsitID
//

import SwiftUI

struct TeacherSelectionView: View {
    @EnvironmentObject var appState: AppState
    @Binding var isPresented: Bool
    @State private var searchText = ""
    @State private var favoriteTeachers: [String] = []
    
    var filteredTeachers: [String] {
        let teachers = appState.availableTeachers
        if searchText.isEmpty {
            return sortedTeachers(teachers)
        }
        return sortedTeachers(teachers.filter { teacher in
            teacher.localizedCaseInsensitiveContains(searchText)
        })
    }
    
    func sortedTeachers(_ teachers: [String]) -> [String] {
        let favorites = StorageService.shared.getFavoriteTeachers()
        return teachers.sorted { teacher1, teacher2 in
            let isFav1 = favorites.contains(teacher1)
            let isFav2 = favorites.contains(teacher2)
            if isFav1 && !isFav2 { return true }
            if !isFav1 && isFav2 { return false }
            if isFav1 && isFav2 {
                let idx1 = favorites.firstIndex(of: teacher1) ?? Int.max
                let idx2 = favorites.firstIndex(of: teacher2) ?? Int.max
                return idx1 < idx2
            }
            return teacher1 < teacher2
        }
    }
    
    var body: some View {
        NavigationView {
            ZStack {
                LinearGradient(
                    colors: [
                        Color(red: 0.043, green: 0.071, blue: 0.125),
                        Color(red: 0.208, green: 0.208, blue: 0.208)
                    ],
                    startPoint: .top,
                    endPoint: .bottom
                )
                .ignoresSafeArea()
                
                VStack(spacing: 0) {
                    // Search bar
                    HStack {
                        HStack {
                            Image(systemName: "magnifyingglass")
                                .foregroundColor(.white.opacity(0.6))
                            TextField("Поиск преподавателя...", text: $searchText)
                                .foregroundColor(.white)
                        }
                        .padding(.horizontal, 12)
                        .padding(.vertical, 10)
                        .glassEffect(cornerRadius: 12)
                        
                        Button(action: {
                            isPresented = false
                        }) {
                            Text("Группы")
                                .font(.system(size: 14, weight: .medium))
                                .foregroundColor(.white)
                                .padding(.horizontal, 12)
                                .padding(.vertical, 10)
                                .glassEffect(cornerRadius: 12)
                        }
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 12)
                    
                    // Teachers list
                    ScrollView {
                        LazyVStack(spacing: 12) {
                            ForEach(filteredTeachers, id: \.self) { teacher in
                                TeacherRow(teacher: teacher, isPresented: $isPresented)
                            }
                        }
                        .padding(.horizontal, 16)
                        .padding(.bottom, 20)
                    }
                }
            }
            .navigationTitle("Выберите преподавателя")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Закрыть") {
                        isPresented = false
                    }
                    .foregroundColor(.white)
                }
            }
        }
        .onAppear {
            favoriteTeachers = StorageService.shared.getFavoriteTeachers()
        }
    }
}

struct TeacherRow: View {
    let teacher: String
    @Binding var isPresented: Bool
    @EnvironmentObject var appState: AppState
    @State private var isFavorite: Bool
    
    init(teacher: String, isPresented: Binding<Bool>) {
        self.teacher = teacher
        self._isPresented = isPresented
        _isFavorite = State(initialValue: StorageService.shared.isFavoriteTeacher(teacher))
    }
    
    var body: some View {
        Button(action: {
            appState.selectTeacher(teacher)
            isPresented = false
        }) {
            HStack(spacing: 12) {
                Image(systemName: "person.fill")
                    .font(.system(size: 16))
                    .foregroundColor(Color(red: 0.659, green: 0.706, blue: 0.996))
                    .frame(width: 32, height: 32)
                    .background(Color(red: 0.659, green: 0.333, blue: 0.969).opacity(0.2))
                    .cornerRadius(10)
                
                VStack(alignment: .leading, spacing: 2) {
                    Text(teacher)
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.white)
                    Text("Преподаватель")
                        .font(.system(size: 13))
                        .foregroundColor(.white.opacity(0.7))
                }
                
                Spacer()
                
                Button(action: {
                    isFavorite.toggle()
                    if isFavorite {
                        StorageService.shared.addFavoriteTeacher(teacher)
                    } else {
                        StorageService.shared.removeFavoriteTeacher(teacher)
                    }
                }) {
                    Image(systemName: isFavorite ? "star.fill" : "star")
                        .font(.system(size: 16))
                        .foregroundColor(isFavorite ? Color(red: 1.0, green: 0.820, blue: 0.376) : .white.opacity(0.35))
                }
            }
            .padding(12)
            .glassEffect(cornerRadius: 12)
        }
        .buttonStyle(PlainButtonStyle())
    }
}

