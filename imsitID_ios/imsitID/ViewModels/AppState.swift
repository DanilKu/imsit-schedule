//
//  AppState.swift
//  imsitID
//

import Foundation
import SwiftUI

@MainActor
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
        // Устанавливаем текущий день недели (1 = Понедельник, 6 = Суббота)
        let calendar = Calendar.current
        let moscowTimeZone = TimeZone(identifier: "Europe/Moscow") ?? TimeZone.current
        var components = calendar.dateComponents(in: moscowTimeZone, from: Date())
        components.timeZone = moscowTimeZone
        if let date = calendar.date(from: components) {
            let dayOfWeek = calendar.component(.weekday, from: date)
            // Конвертируем: 1=Вс, 2=Пн, ..., 7=Сб -> 1=Пн, 2=Вт, ..., 6=Сб
            let day = dayOfWeek == 1 ? 6 : dayOfWeek - 1
            currentDay = min(max(day, 1), 6) // Ограничиваем 1-6
        }
        
        // Определяем текущую неделю (четная/нечетная)
        let weekOfYear = calendar.component(.weekOfYear, from: Date())
        currentWeek = (weekOfYear % 2 == 0) ? 1 : 2
        
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
            self.availableGroups = groups
        } catch {
            print("Error loading groups: \(error)")
        }
    }
    
    func loadAvailableTeachers() async {
        do {
            let teachers = try await apiService.fetchTeachers()
            self.availableTeachers = teachers
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
        isLoading = true
        errorMessage = nil
        
        // Сначала пытаемся загрузить из кеша
        if let cachedSchedule = loadCachedSchedule() {
            self.schedule = cachedSchedule
        }
        
        do {
            let fullSchedule: Schedule
            
            if let group = selectedGroup {
                print("DEBUG: Loading full schedule for group: '\(group)'")
                print("DEBUG: Group name length: \(group.count), UTF-8 bytes: \(group.data(using: .utf8)?.count ?? 0)")
                fullSchedule = try await apiService.fetchFullSchedule(group: group, teacher: nil)
            } else if let teacher = selectedTeacher {
                print("DEBUG: Loading full schedule for teacher: '\(teacher)'")
                print("DEBUG: Teacher name length: \(teacher.count), UTF-8 bytes: \(teacher.data(using: .utf8)?.count ?? 0)")
                fullSchedule = try await apiService.fetchFullSchedule(group: nil, teacher: teacher)
            } else {
                isLoading = false
                return
            }
            
            self.schedule = fullSchedule
            self.isLoading = false
            
            print("DEBUG: Loaded schedule - week1 days: \(fullSchedule.week1.keys.sorted()), week2 days: \(fullSchedule.week2.keys.sorted())")
            print("DEBUG: Current week=\(currentWeek), day=\(currentDay)")
            print("DEBUG: Lessons for current day: \(fullSchedule.getLessons(week: currentWeek, day: currentDay).count)")
            
            // Сохраняем в кеш
            if let group = selectedGroup {
                storageService.saveSchedule(fullSchedule, for: group, teacher: nil)
            } else if let teacher = selectedTeacher {
                storageService.saveSchedule(fullSchedule, for: nil, teacher: teacher)
            }
            
            await updateCurrentLesson()
        } catch {
            self.isLoading = false
            print("DEBUG: Error loading schedule: \(error)")
            if let group = selectedGroup {
                print("DEBUG: Failed for group: '\(group)'")
            } else if let teacher = selectedTeacher {
                print("DEBUG: Failed for teacher: '\(teacher)'")
            }
            // Если есть кеш, не показываем ошибку
            if self.schedule.week1.isEmpty && self.schedule.week2.isEmpty {
                self.errorMessage = error.localizedDescription
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
            
            print("DEBUG: Current lesson response - success: \(response.success)")
            print("DEBUG: Current lesson: \(response.currentLesson != nil ? "exists" : "nil")")
            print("DEBUG: Next lesson: \(response.nextLesson != nil ? "exists" : "nil")")
            
            if let current = response.currentLesson {
                print("DEBUG: Current lesson subject: \(current.subjectName)")
            }
            if let next = response.nextLesson {
                print("DEBUG: Next lesson subject: \(next.subjectName)")
            }
            
            self.currentLesson = response.currentLesson
            self.nextLesson = response.nextLesson
        } catch {
            print("DEBUG: Error updating current lesson: \(error)")
        }
    }
    
    func refreshSchedule() async {
        await loadFullSchedule()
    }
    
    // MARK: - Current Schedule
    
    func getCurrentLessons() -> [Lesson] {
        let lessons = schedule.getLessons(week: currentWeek, day: currentDay)
        print("DEBUG: Getting lessons for week=\(currentWeek), day=\(currentDay), count=\(lessons.count)")
        print("DEBUG: Schedule week1 keys: \(schedule.week1.keys.sorted())")
        print("DEBUG: Schedule week2 keys: \(schedule.week2.keys.sorted())")
        return lessons
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

