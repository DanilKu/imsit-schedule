//
//  ScheduleListView.swift
//  imsitID
//

import SwiftUI

struct ScheduleListView: View {
    @EnvironmentObject var appState: AppState
    
    let fullDayNames = ["Понедельник", "Вторник", "Среда", "Четверг", "Пятница", "Суббота"]
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text(fullDayNames[appState.currentDay - 1])
                .font(.system(size: 18, weight: .semibold))
                .foregroundColor(.white)
                .padding(.horizontal, 4)
            
            let lessons = appState.getCurrentLessons()
            
            if lessons.isEmpty {
                EmptyScheduleView()
            } else {
                ForEach(lessons) { lesson in
                    LessonCard(lesson: lesson)
                }
            }
        }
    }
}

struct LessonCard: View {
    let lesson: Lesson
    @EnvironmentObject var appState: AppState
    
    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Label(lesson.timeRange, systemImage: "clock")
                    .font(.system(size: 12, weight: .medium))
                    .foregroundColor(.white.opacity(0.75))
                    .textCase(.uppercase)
                    .tracking(0.3)
            }
            
            Text(lesson.subjectName)
                .font(.system(size: 16, weight: .semibold))
                .foregroundColor(.white)
                .lineLimit(2)
            
            HStack(spacing: 8) {
                HStack(spacing: 4) {
                    Image(systemName: "door.left.hand.open")
                        .font(.system(size: 10))
                    Text(lesson.roomNumber)
                        .font(.system(size: 12, weight: .medium))
                }
                .foregroundColor(.white)
                .padding(.horizontal, 8)
                .padding(.vertical, 4)
                .background(Color.white.opacity(0.15))
                .cornerRadius(12)
                .overlay(
                    RoundedRectangle(cornerRadius: 12)
                        .stroke(Color.white.opacity(0.2), lineWidth: 1)
                )
                
                HStack(spacing: 4) {
                    if appState.viewMode == .teacher, let groups = lesson.groups, !groups.isEmpty {
                        Image(systemName: "person.3.fill")
                            .font(.system(size: 10))
                        Text(groups.joined(separator: ", "))
                            .font(.system(size: 12, weight: .medium))
                    } else if appState.viewMode == .teacher, let groupName = lesson.groupName, !groupName.isEmpty {
                        Image(systemName: "person.3.fill")
                            .font(.system(size: 10))
                        Text(groupName)
                            .font(.system(size: 12, weight: .medium))
                    } else {
                        Image(systemName: "person.fill")
                            .font(.system(size: 10))
                        Text(lesson.teacherName)
                            .font(.system(size: 12, weight: .medium))
                    }
                }
                .foregroundColor(.white.opacity(0.9))
                .padding(.horizontal, 8)
                .padding(.vertical, 4)
                .background(Color.white.opacity(0.1))
                .cornerRadius(12)
                .overlay(
                    RoundedRectangle(cornerRadius: 12)
                        .stroke(Color.white.opacity(0.15), lineWidth: 1)
                )
            }
        }
        .padding(12)
        .glassEffect(cornerRadius: 12)
        .overlay(
            RoundedRectangle(cornerRadius: 12)
                .stroke(
                    LinearGradient(
                        colors: [
                            Color.white.opacity(0.2),
                            Color.white.opacity(0.05)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    ),
                    lineWidth: 1
                )
        )
    }
}

struct EmptyScheduleView: View {
    var body: some View {
        VStack(spacing: 16) {
            Image(systemName: "cup.and.saucer.fill")
                .font(.system(size: 48))
                .foregroundColor(Color(red: 0.376, green: 0.510, blue: 0.980))
                .frame(width: 60, height: 60)
                .background(
                    LinearGradient(
                        colors: [
                            Color.white.opacity(0.15),
                            Color.white.opacity(0.08)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .cornerRadius(16)
                .overlay(
                    RoundedRectangle(cornerRadius: 16)
                        .stroke(Color.white.opacity(0.2), lineWidth: 1)
                )
            
            Text("Сегодня пар нет")
                .font(.system(size: 18, weight: .semibold))
                .foregroundColor(.white)
            
            Text("Отдохните или выберите другой день")
                .font(.system(size: 14))
                .foregroundColor(.white.opacity(0.75))
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 40)
        .glassEffect(cornerRadius: 16)
    }
}

