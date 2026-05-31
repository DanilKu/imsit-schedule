//
//  AppState.swift
//  imsitID
//

import Foundation
import SwiftUI

class AppState: ObservableObject {
    @Published var selectedGroup: String?
    @Published var selectedTeacher: String?
    @Published var viewMode: ViewMode = .group
    @Published var currentWeek: Int = 1
    @Published var currentDay: Int = 1
    @Published var schedule: Schedule = Schedule()
    @Published var currentLesson: Lesson?
    @Published var nextLesson: Lesson?
    @Published var isLoading: Bool = false
    @Published var errorMessage: String?
    
    @Published var availableGroups: [String] = []
    @Published var availableTeachers: [String] = []
    
    private let apiService = APIService.shared
    private let storageService = StorageService.shared
    
    init() {
        loadFromStorage()
    }
    
    // MARK: - Storage
    
    func loadFromStorage() {
        if let group = storageService.getSelectedGroup() {
            selectedGroup = group
            viewMode = .group
            if let cachedSchedule = storageService.loadSchedule(for: group, teacher: nil) {
                schedule = cachedSchedule
            }
        } else if let teacher = storageService.getSelectedTeacher() {
            selectedTeacher = teacher
            viewMode = .teacher
            if let cachedSchedule = storageService.loadSchedule(for: nil, teacher: teacher) {
                schedule = cachedSchedule
            }
        }
    }
    
    // MARK: - Data Loading
    
    func loadAvailableGroups() async {
        do {
            let groups = try await apiService.fetchGroups()
            await MainActor.run {
                self.availableGroups = groups
            }
        } catch {
            print("Error loading groups: \(error)")
        }
    }
    
    func loadAvailableTeachers() async {
        do {
            let teachers = try await apiService.fetchTeachers()
            await MainActor.run {
                self.availableTeachers = teachers
            }
        } catch {
            print("Error loading teachers: \(error)")
        }
    }
    
    func selectGroup(_ group: String) {
        selectedGroup = group
        selectedTeacher = nil
        viewMode = .group
        storageService.saveSelectedGroup(group)
        Task {
            await loadFullSchedule()
        }
    }
    
    func selectTeacher(_ teacher: String) {
        selectedTeacher = teacher
        selectedGroup = nil
        viewMode = .teacher
        storageService.saveSelectedTeacher(teacher)
        Task {
            await loadFullSchedule()
        }
    }
    
    func loadFullSchedule() async {
        await MainActor.run {
            isLoading = true
            errorMessage = nil
        }
        
        // Сначала пытаемся загрузить из кеша
        if let cachedSchedule = loadCachedSchedule() {
            await MainActor.run {
                self.schedule = cachedSchedule
            }
        }
        
        do {
            let fullSchedule: Schedule
            
            if let group = selectedGroup {
                fullSchedule = try await apiService.fetchFullSchedule(group: group, teacher: nil)
            } else if let teacher = selectedTeacher {
                fullSchedule = try await apiService.fetchFullSchedule(group: nil, teacher: teacher)
            } else {
                await MainActor.run {
                    isLoading = false
                }
                return
            }
            
            await MainActor.run {
                self.schedule = fullSchedule
                self.isLoading = false
                
                // Сохраняем в кеш
                if let group = selectedGroup {
                    storageService.saveSchedule(fullSchedule, for: group, teacher: nil)
                } else if let teacher = selectedTeacher {
                    storageService.saveSchedule(fullSchedule, for: nil, teacher: teacher)
                }
            }
            
            await updateCurrentLesson()
        } catch {
            await MainActor.run {
                self.isLoading = false
                // Если есть кеш, не показываем ошибку
                if self.schedule.week1.isEmpty && self.schedule.week2.isEmpty {
                    self.errorMessage = error.localizedDescription
                }
            }
        }
    }
    
    private func loadCachedSchedule() -> Schedule? {
        if let group = selectedGroup {
            return storageService.loadSchedule(for: group, teacher: nil)
        } else if let teacher = selectedTeacher {
            return storageService.loadSchedule(for: nil, teacher: teacher)
        }
        return nil
    }
    
    func updateCurrentLesson() async {
        do {
            let response: CurrentLessonResponse
            
            if let group = selectedGroup {
                response = try await apiService.fetchCurrentLesson(group: group, teacher: nil)
            } else if let teacher = selectedTeacher {
                response = try await apiService.fetchCurrentLesson(group: nil, teacher: teacher)
            } else {
                return
            }
            
            await MainActor.run {
                self.currentLesson = response.currentLesson
                self.nextLesson = response.nextLesson
            }
        } catch {
            print("Error updating current lesson: \(error)")
        }
    }
    
    func refreshSchedule() async {
        await loadFullSchedule()
    }
    
    // MARK: - Current Schedule
    
    func getCurrentLessons() -> [Lesson] {
        return schedule.getLessons(week: currentWeek, day: currentDay)
    }
    
    func switchWeek(_ week: Int) {
        currentWeek = week
    }
    
    func switchDay(_ day: Int) {
        currentDay = day
    }
}

enum ViewMode {
    case group
    case teacher
}

