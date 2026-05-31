//
//  StorageService.swift
//  imsitID
//

import Foundation

class StorageService {
    static let shared = StorageService()
    
    private let userDefaults = UserDefaults.standard
    private let scheduleKey = "cached_schedule"
    private let selectedGroupKey = "selected_group"
    private let selectedTeacherKey = "selected_teacher"
    private let viewModeKey = "view_mode"
    private let favoritesGroupsKey = "favorites_groups"
    private let favoritesTeachersKey = "favorites_teachers"
    
    private init() {}
    
    // MARK: - Schedule Storage
    
    func saveSchedule(_ schedule: Schedule, for group: String? = nil, teacher: String? = nil) {
        do {
            let encoder = JSONEncoder()
            let data = try encoder.encode(schedule)
            
            let key = group != nil ? "\(scheduleKey)_group_\(group!)" : "\(scheduleKey)_teacher_\(teacher!)"
            userDefaults.set(data, forKey: key)
            
            // Сохраняем метаданные
            if let group = group {
                userDefaults.set(group, forKey: selectedGroupKey)
                userDefaults.set("group", forKey: viewModeKey)
            } else if let teacher = teacher {
                userDefaults.set(teacher, forKey: selectedTeacherKey)
                userDefaults.set("teacher", forKey: viewModeKey)
            }
        } catch {
            print("Error saving schedule: \(error)")
        }
    }
    
    func loadSchedule(for group: String? = nil, teacher: String? = nil) -> Schedule? {
        let key = group != nil ? "\(scheduleKey)_group_\(group!)" : "\(scheduleKey)_teacher_\(teacher!)"
        
        guard let data = userDefaults.data(forKey: key) else {
            return nil
        }
        
        do {
            let decoder = JSONDecoder()
            return try decoder.decode(Schedule.self, from: data)
        } catch {
            print("Error loading schedule: \(error)")
            return nil
        }
    }
    
    // MARK: - Selection Storage
    
    func saveSelectedGroup(_ group: String) {
        userDefaults.set(group, forKey: selectedGroupKey)
        userDefaults.set("group", forKey: viewModeKey)
        userDefaults.removeObject(forKey: selectedTeacherKey)
    }
    
    func saveSelectedTeacher(_ teacher: String) {
        userDefaults.set(teacher, forKey: selectedTeacherKey)
        userDefaults.set("teacher", forKey: viewModeKey)
        userDefaults.removeObject(forKey: selectedGroupKey)
    }
    
    func getSelectedGroup() -> String? {
        return userDefaults.string(forKey: selectedGroupKey)
    }
    
    func getSelectedTeacher() -> String? {
        return userDefaults.string(forKey: selectedTeacherKey)
    }
    
    func getViewMode() -> String {
        return userDefaults.string(forKey: viewModeKey) ?? "group"
    }
    
    // MARK: - Favorites
    
    func getFavoriteGroups() -> [String] {
        return userDefaults.stringArray(forKey: favoritesGroupsKey) ?? []
    }
    
    func getFavoriteTeachers() -> [String] {
        return userDefaults.stringArray(forKey: favoritesTeachersKey) ?? []
    }
    
    func addFavoriteGroup(_ group: String) {
        var favorites = getFavoriteGroups()
        if !favorites.contains(group) {
            favorites.insert(group, at: 0)
            userDefaults.set(favorites, forKey: favoritesGroupsKey)
        }
    }
    
    func addFavoriteTeacher(_ teacher: String) {
        var favorites = getFavoriteTeachers()
        if !favorites.contains(teacher) {
            favorites.insert(teacher, at: 0)
            userDefaults.set(favorites, forKey: favoritesTeachersKey)
        }
    }
    
    func removeFavoriteGroup(_ group: String) {
        var favorites = getFavoriteGroups()
        favorites.removeAll { $0 == group }
        userDefaults.set(favorites, forKey: favoritesGroupsKey)
    }
    
    func removeFavoriteTeacher(_ teacher: String) {
        var favorites = getFavoriteTeachers()
        favorites.removeAll { $0 == teacher }
        userDefaults.set(favorites, forKey: favoritesTeachersKey)
    }
    
    func isFavoriteGroup(_ group: String) -> Bool {
        return getFavoriteGroups().contains(group)
    }
    
    func isFavoriteTeacher(_ teacher: String) -> Bool {
        return getFavoriteTeachers().contains(teacher)
    }
}

